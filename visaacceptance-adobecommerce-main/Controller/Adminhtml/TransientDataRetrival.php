<?php

declare(strict_types=1);

namespace CyberSource\Payment\Controller\Adminhtml;

use Magento\Quote\Api\Data\PaymentInterface;
use CyberSource\Payment\Model\Ui\ConfigProvider;
use Magento\Framework\Url\DecoderInterface;

class TransientDataRetrival extends \Magento\Backend\App\Action
{
    public const COMMAND_CODE = 'get_token_detail';
    public const CODE = 'authorize';
    public const SET_UP = 'payerauthSetup';
    public const PAYER_AUTH_SANDBOX_URL = 'https://centinelapistag.cardinalcommerce.com';
    public const PAYER_AUTH_PROD_URL = 'https://centinelapi.cardinalcommerce.com';

    private $resultJsonFactory;
    private $commandManager;
    private $sessionManager;
    private $logger;
    private $formKeyValidator;
    private $quoteRepository;
    private $checkoutSession;
    private $quoteManagement;
    private $config;
    private $eventManager;
    private $paymentFailureRouteProvider;
    private $sessionStorage;
    public $customerSession;
    private $paSetupCall;
    private $cartRepository;
    protected $urlDecoder;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Payment\Gateway\Command\CommandManagerInterface $commandManager,
        \Magento\Framework\Session\SessionManagerInterface $sessionManager,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \CyberSource\Payment\Model\LoggerInterface $logger,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \CyberSource\Payment\Model\Config $config,
        \CyberSource\Payment\Model\Checkout\PaymentFailureRouteProviderInterface $paymentFailureRouteProvider,
        \Magento\Framework\Session\StorageInterface $sessionStorage,
        \Magento\Customer\Model\Session $customerSession,
        \CyberSource\Payment\Controller\Adminhtml\PaSetup $paSetupCall,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        DecoderInterface $urlDecoder
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->commandManager = $commandManager;
        $this->sessionManager = $sessionManager;
        $this->formKeyValidator = $formKeyValidator;
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->checkoutSession = $checkoutSession;
        $this->quoteManagement = $quoteManagement;
        $this->config = $config;
        $this->paymentFailureRouteProvider = $paymentFailureRouteProvider;
        $this->sessionStorage = $sessionStorage;
        $this->customerSession = $customerSession;
        $this->paSetupCall = $paSetupCall;
        $this->cartRepository = $cartRepository;
        $this->urlDecoder = $urlDecoder;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        try {
            $data = $this->getRequest()->getPostValue('transientToken');
            $vaultIsEnabled = $this->getRequest()->getPostValue('vault');
            $browserDetails = $this->getRequest()->getParams();
            $this->sessionStorage->setData('vaultIsEnabled', $vaultIsEnabled);
            $this->sessionStorage->setData('browser_details', $browserDetails);

            $decoded_transient_token = json_decode($this->urlDecoder->decode(explode('.', $data)[1]), true);
            $quote = $this->sessionManager->getQuote();
            $quote->getPayment()->setAdditionalInformation("paymentToken", base64_encode($data));
            $this->sessionStorage->setData("paymentToken", base64_encode($data));
            if (isset($decoded_transient_token['content']['processingInformation']['paymentSolution']['value'])) {
                $paymentSolution = $decoded_transient_token['content']['processingInformation']['paymentSolution']['value'] ?? '';
                $quote->getPayment()->setAdditionalInformation('paymentSolution', $paymentSolution);
            }
            $commandResult = $this->commandManager->executeByCode(
                self::COMMAND_CODE,
                $quote->getPayment()
            );

            $response = $commandResult->get();
            $expMonth = $response['paymentInformation']['card']['expirationMonth'] ?? null;
            $expYear = $response['paymentInformation']['card']['expirationYear'] ?? null;
            $maskedPan = $response['paymentInformation']['card']['number'] ?? null;
            $cardType = $response['paymentInformation']['card']['type'] ?? null;
            $quote->getPayment()->setAdditionalInformation('cardType', $cardType);
            $quote->getPayment()->setAdditionalInformation('maskedPan', $maskedPan);
            $quote->getPayment()->setAdditionalInformation('expDate', "$expMonth-$expYear");
            $isRegisteredUser = $this->customerSession->isLoggedIn();
            $quote->getPayment()->setAdditionalInformation(
                \Magento\Vault\Model\Ui\VaultConfigProvider::IS_ACTIVE_CODE,
                (bool)false
            );

            if ($vaultIsEnabled == "true" && !isset($decoded_transient_token['content']['processingInformation']['paymentSolution']) && $isRegisteredUser == "true"){
                $quote->getPayment()->setAdditionalInformation(
                    \Magento\Vault\Model\Ui\VaultConfigProvider::IS_ACTIVE_CODE,
                    (bool)$vaultIsEnabled
                );
            }

            $quote->save();
            if ($this->config->isPayerAuthEnabled() == true
                && (!isset($decoded_transient_token['content']['processingInformation']['paymentSolution'])
                    || (isset($decoded_transient_token['content']['processingInformation']['paymentSolution'])
                        && $decoded_transient_token['content']['processingInformation']['paymentSolution']['value'] === '012'
                        && $decoded_transient_token['metadata']['cardholderAuthenticationStatus'] === false)
                )
            ) {
                $payment = $quote->getPayment();
                $data = [
                    PaymentInterface::KEY_METHOD => $payment->getMethod() ?? ConfigProvider::CODE
                ];

                if ($method = $this->getRequest()->getParam('method')) {
                    $data[PaymentInterface::KEY_METHOD] = $method;
                };

                if ($additionalData = $this->getRequest()->getParam('additional_data')) {
                    unset($additionalData['cvv']);
                    $data['additional_data'] = $additionalData;
                };
                $SetUpResult = $this->commandManager->executeByCode(self::SET_UP, $quote->getPayment());
                $this->cartRepository->save($quote);

                $result->setData(array_merge(
                    ['success' => true],
                    ['sandbox' => self::PAYER_AUTH_SANDBOX_URL],
                    ['production' => self::PAYER_AUTH_PROD_URL],
                    $SetUpResult->get()
                ));
            } else {
                $this->logger->debug("admin transient data saved");
                $this->quoteRepository->save($quote);
                $result->setData([
                    'status' => 200,
                    'message' => 'Payment details collected successfully.'
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ['exception' => $e]);
            $resultData = [
                'status' => 500,
                'message' => $e->getMessage(),
                'redirect_url' => $this->_url->getUrl($this->paymentFailureRouteProvider->getFailureRoutePath())
            ];
            $result->setData($resultData);
        }

        return $result;
    }
}
