<?php
/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */
declare(strict_types=1);
namespace CyberSource\Payment\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session\Quote as SessionQuote;
use Magento\Framework\Url\DecoderInterface;
use CyberSource\Payment\Gateway\PaEnrolledException;

class CaptureContextRequest extends Action
{
    public const COMMAND_CODE = 'generate_capture_context';

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    private $resultJsonFactory;
     * @var \Magento\Payment\Gateway\Command\CommandManagerInterface
     */
    private $commandManager;
    /**
     * @var \Magento\Framework\Session\SessionManagerInterface
     */
    private $sessionManager;
    /** @var SessionQuote */
    private $sessionQuote;
    /**
     * @var \CyberSource\Payment\Model\LoggerInterface
     */
    private $logger;
    /**
     * @var \Magento\Quote\Model\QuoteRepository
     */
    private $quoteRepository;
    /**
     * @var \CyberSource\Payment\Model\Config
     */
    private $config;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;
    /** @var DecoderInterface */
    protected $urlDecoder;
    /**
     * CaptureContextRequest constructor.
     */
    public function __construct(
        Context $context,
        \Magento\Payment\Gateway\Command\CommandManagerInterface $commandManager,
        \Magento\Framework\Session\SessionManagerInterface $sessionManager,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \CyberSource\Payment\Model\LoggerInterface $logger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \CyberSource\Payment\Model\Config $config,
        DecoderInterface $urlDecoder,
        SessionQuote $sessionQuote = null
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->commandManager = $commandManager;
        $this->sessionManager = $sessionManager;
        $this->logger = $logger;
        $this->quoteRepository = $quoteRepository;
        $this->checkoutSession = $checkoutSession;
        $this->config = $config;
        $this->urlDecoder = $urlDecoder;
        $this->sessionQuote = $sessionQuote ?: $context->getObjectManager()->get(SessionQuote::class);
    }

    /**
     * Creates SA request JSON
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $quote = $this->sessionQuote->getQuote();
            $data = $this->getRequest()->getParams();
            $guestEmail = $data['guestEmail'] ?? null;

            if ($guestEmail && !$quote->getCustomerId()) {
                $quote->setCustomerEmail($guestEmail);
                $quote->getBillingAddress()->setEmail($guestEmail);
            }

            if (!$this->getRequest()->isPost()) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Wrong method.'));
            }

            if (!$quote || !$quote->getId()) {
                $this->logger->info('Unable to build Capture Context request');
                throw new \Magento\Framework\Exception\LocalizedException(__('Something went wrong. Please try again.'));
            }

            $commandResult = $this->commandManager->executeByCode(self::COMMAND_CODE, $quote->getPayment());
            $commandResult = $commandResult->get();

            $token = $commandResult['response'];
            if (isset($token['rmsg']) && $token['rmsg'] == "Authentication Failed") {
                $this->logger->info('Unable to load the Unified Checkout form.');
                throw new PaEnrolledException(__('Something went wrong. Please try again.'), 401);
            }

            $captureContext = json_decode($this->urlDecoder->decode(str_replace('_', '/', str_replace('-', '+', explode('.', $token)[1]))));
            $data['captureContext'] = $commandResult;
            $data['unified_checkout_client_library'] = $captureContext->ctx[0]->data->clientLibrary ?? null;

            $this->quoteRepository->save($quote);

            $result->setData([
                'success' => true,
                'captureContext' => $commandResult['response'],
                'unified_checkout_client_library' => $data['unified_checkout_client_library'],
                'layoutSelected' => $this->config->getUcLayout(),
                'setupcall' => $this->config->isPayerAuthEnabled()
            ]);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ['exception' => $e]);
            $this->logger->info('Unable to build Capture Context.');
            $result->setData(['error_msg' => __('Something went wrong. Please try again.')]);
        }

        return $result;
    }

    /**
     * ACL check for admin access
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('CyberSource_Payment::manage');
    }
}
    