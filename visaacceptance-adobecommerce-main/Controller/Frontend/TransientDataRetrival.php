<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Controller\Frontend;

use Magento\Quote\Api\Data\PaymentInterface;
use CyberSource\Payment\Model\Ui\ConfigProvider;
use Magento\Framework\Url\DecoderInterface;

class TransientDataRetrival extends \Magento\Framework\App\Action\Action
{
    public const COMMAND_CODE = 'get_token_detail';
    public const CODE = 'authorize';
    public const SET_UP = 'payerauthSetup';
    public const PAYER_AUTH_SANDBOX_URL = 'https://centinelapistag.cardinalcommerce.com';
    public const PAYER_AUTH_PROD_URL = 'https://centinelapi.cardinalcommerce.com';

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var \Magento\Payment\Gateway\Command\CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var \Magento\Framework\Session\SessionManagerInterface
     */
    private $sessionManager;

    /**
     * @var \CyberSource\Payment\Model\LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Framework\Data\Form\FormKey\Validator
     */
    private $formKeyValidator;

    /**
     * @var \Magento\Quote\Model\QuoteRepository
     */
    private $quoteRepository;

    /**
     *
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var  \Magento\Quote\Model\QuoteManagement
     */
    private $quoteManagement;

    /**
     * @var \CyberSource\Payment\Model\Config
     */
    private $config;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $eventManager;

    /**
     * @var \CyberSource\Payment\Model\Checkout\PaymentFailureRouteProviderInterface
     */
    private $paymentFailureRouteProvider;

    /**
     * @var \Magento\Framework\Session\StorageInterface
     */
    private $sessionStorage;

    /**
     * @var \Magento\Customer\Model\Session
     */
    public $customerSession;

    /**
     * @var \CyberSource\Payment\Controller\Frontend\PaSetup
     */
    private $paSetupCall;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var \Magento\Framework\Url\DecoderInterface
     */
    protected $urlDecoder;

    /**
     * TokenRequest constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Payment\Gateway\Command\CommandManagerInterface $commandManager
     * @param \Magento\Framework\Session\SessionManagerInterface $sessionManager
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator
     * @param \Magento\Quote\Model\QuoteRepository $quoteRepository
     * @param \CyberSource\Payment\Model\LoggerInterface $logger
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Quote\Model\QuoteManagement $quoteManagement
     * @param \CyberSource\Payment\Model\Config $config
     * @param \CyberSource\Payment\Model\Checkout\PaymentFailureRouteProviderInterface $paymentFailureRouteProvider
     * @param \Magento\Framework\Session\StorageInterface $sessionStorage
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \CyberSource\Payment\Controller\Frontend\PaSetup $paSetupCall
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param \Magento\Framework\Url\DecoderInterface $urlDecoder
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
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
        \CyberSource\Payment\Controller\Frontend\PaSetup $paSetupCall,
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

    /**
     * Creates SA request JSON
     *
     * @return \Magento\Framework\Controller\ResultInterface|\Magento\Framework\App\ResponseInterface
     */
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
            $quote = $this->checkoutSession->getQuote();
            $quote->getPayment()->setAdditionalInformation("paymentToken", base64_encode($data));
            $this->sessionStorage->setData("paymentToken", base64_encode($data));
            if (isset($decoded_transient_token['content']['processingInformation']['paymentSolution']['value'])) {
                $paymentSolution = $decoded_transient_token['content']['processingInformation']['paymentSolution']['value']
                    ?? '';
                $quote->getPayment()->setAdditionalInformation(
                    'paymentSolution',
                    $paymentSolution
                );
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
            
            if ($vaultIsEnabled == "true" && !isset(
                $decoded_transient_token['content']['processingInformation']['paymentSolution']
            )
                && $isRegisteredUser == "true"
            ){
                $quote->getPayment()->setAdditionalInformation(
                    \Magento\Vault\Model\Ui\VaultConfigProvider::IS_ACTIVE_CODE,
                    (bool)$vaultIsEnabled
                );
            }

            $quote->save();
            if ($this->config->isPayerAuthEnabled() == true
                && 
                (!isset($decoded_transient_token['content']['processingInformation']['paymentSolution'])
                    || 
                    (isset($decoded_transient_token['content']['processingInformation']['paymentSolution'])
                        && $decoded_transient_token['content']['processingInformation']['paymentSolution']['value'] ===
                        '012' && $decoded_transient_token['metadata']['cardholderAuthenticationStatus'] === false)
                        
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
                  $SetUpResult = $this->commandManager->executeByCode(
                      self::SET_UP,
                      $quote->getPayment()
                  );
                    $this->cartRepository->save($quote);

                    $result->setData(array_merge(
                        ['success' => true],
                        ['sandbox' => self::PAYER_AUTH_SANDBOX_URL],
                        ['production' => self::PAYER_AUTH_PROD_URL],
                        $SetUpResult->get()
                    ));
            } else {
                $this->logger->debug("inside else block");

                $quote->setPaymentMethod(ConfigProvider::CODE);
                $quote->setInventoryProcessed(false);
                $quote->getPayment()->importData(['method' => ConfigProvider::CODE]);
                $quote->collectTotals();
                $this->quoteRepository->save($quote);

                $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
                $this->checkoutSession->setLastQuoteId($quote->getId());
                $this->checkoutSession->clearHelperData();

                $order = $this->quoteManagement->submit($quote);
                $this->eventManager->dispatch(
                    'cybersource_quote_submit_success',
                    [
                    'order' => $order,
                    'quote' => $quote
                    ]
                );

                $this->checkoutSession->setLastOrderId($order->getId());
                $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
                $this->checkoutSession->setLastOrderStatus($order->getStatus());

                $successValidator = $this->_objectManager->get(\Magento\Checkout\Model\Session\SuccessValidator::class);

                if (!$successValidator->isValid()) {
                    $resultData = [
                    'status' => 500,
                    'message' => 'Unable to place order. Please try again.',
                    'redirect_url' => $this->_url->getUrl($this->paymentFailureRouteProvider->getFailureRoutePath())
                    ];
                } else {
                    $resultData = [
                    'status' => 200,
                    'message' => 'Your order has been created successfully.',
                    'redirect_url' => $this->_url->getUrl('checkout/onepage/success')
                    ];
                }

                $result->setData($resultData);
                $this->quoteRepository->save($quote);
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
