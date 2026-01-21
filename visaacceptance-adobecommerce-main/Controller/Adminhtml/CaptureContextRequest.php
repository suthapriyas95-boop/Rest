<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Controller\Adminhtml;

use CyberSource\Payment\Gateway\PaEnrolledException;
use Magento\Backend\App\Action;
use Magento\Backend\Model\Session\Quote as AdminQuoteSession;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Url\DecoderInterface;
use Magento\Payment\Gateway\Command\CommandManagerInterface;

class CaptureContextRequest extends Action
{
    public const COMMAND_CODE = 'generate_capture_context';
    public const ADMIN_RESOURCE = 'Magento_Sales::sales';

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var \Magento\Payment\Gateway\Command\CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var \Magento\Backend\Model\Session\Quote
     */
    private $adminQuoteSession;

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
     * @var \Magento\Framework\Url\DecoderInterface
     */
    private $urlDecoder;

    /**
     * CaptureContextRequest constructor.
     *
     * @param Action\Context $context
     * @param CommandManagerInterface $commandManager
     * @param JsonFactory $resultJsonFactory
     * @param AdminQuoteSession $adminQuoteSession
     * @param \Magento\Quote\Model\QuoteRepository $quoteRepository
     * @param \CyberSource\Payment\Model\LoggerInterface $logger
     * @param \CyberSource\Payment\Model\Config $config
     * @param DecoderInterface $urlDecoder
     */
    public function __construct(
        Action\Context $context,
        CommandManagerInterface $commandManager,
        JsonFactory $resultJsonFactory,
        AdminQuoteSession $adminQuoteSession,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \CyberSource\Payment\Model\LoggerInterface $logger,
        \CyberSource\Payment\Model\Config $config,
        DecoderInterface $urlDecoder
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->commandManager = $commandManager;
        $this->adminQuoteSession = $adminQuoteSession;
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
        $this->config = $config;
        $this->urlDecoder = $urlDecoder;
    }

    /**
     * Creates SA request JSON for admin order create
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $this->logger->info('Admin capture context request received.');
            if (!$this->getRequest()->isPost()) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Wrong method.'));
            }

            $quote = $this->adminQuoteSession->getQuote();
            if (!$quote || !$quote->getId()) {
                $this->logger->info('Unable to build Capture Context request for admin quote');
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Something went wrong. Please try again.')
                );
            }
            $this->logger->info('Admin capture context quote loaded.', ['quote_id' => $quote->getId()]);

            $commandResult = $this->commandManager->executeByCode(
                self::COMMAND_CODE,
                $quote->getPayment()
            )->get();
            $this->logger->info('Admin capture context command executed.');

            $token = $commandResult['response'] ?? null;
            if (is_array($token) && isset($token['rmsg']) && $token['rmsg'] === 'Authentication Failed') {
                $this->logger->info('Unable to load the Unified Checkout form.');
                throw new PaEnrolledException(
                    __('Something went wrong. Please try again.'),
                    401
                );
            }

            $tokenString = (string) $token;
            $payload = explode('.', $tokenString)[1] ?? '';
            $payload = str_replace('_', '/', str_replace('-', '+', $payload));
            $captureContext = json_decode($this->urlDecoder->decode($payload));

            $this->quoteRepository->save($quote);

            $result->setData(
                [
                    'success' => true,
                    'captureContext' => $commandResult['response'],
                    'unified_checkout_client_library' => $captureContext->ctx[0]->data->clientLibrary ?? null,
                    'layoutSelected' => $this->config->getUcLayout(),
                    'setupcall' => $this->config->isPayerAuthEnabled()
                ]
            );
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ['exception' => $e]);
            $this->logger->info('Unable to build Capture Context for admin order.');
            $result->setData(['error_msg' => __('Something went wrong. Please try again.')]);
        }

        return $result;
    }
}
