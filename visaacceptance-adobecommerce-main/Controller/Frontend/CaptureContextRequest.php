<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Controller\Frontend;

use Magento\Framework\Url\DecoderInterface;
use CyberSource\Payment\Gateway\PaEnrolledException;

class CaptureContextRequest extends \Magento\Framework\App\Action\Action
{
    public const COMMAND_CODE = 'generate_capture_context';

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
    protected $checkoutSession;

     /**
      * @var \Magento\Framework\Url\DecoderInterface
      */
    protected $urlDecoder;

    /**
     * CaptureContextRequest constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Payment\Gateway\Command\CommandManagerInterface $commandManager
     * @param \Magento\Framework\Session\SessionManagerInterface $sessionManager
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Quote\Model\QuoteRepository $quoteRepository
     * @param \CyberSource\Payment\Model\LoggerInterface $logger
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \CyberSource\Payment\Model\Config $config
     * @param \Magento\Framework\Url\DecoderInterface $urlDecoder
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Payment\Gateway\Command\CommandManagerInterface $commandManager,
        \Magento\Framework\Session\SessionManagerInterface $sessionManager,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \CyberSource\Payment\Model\LoggerInterface $logger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \CyberSource\Payment\Model\Config $config,
        DecoderInterface $urlDecoder
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

            /** @var \Magento\Quote\Model\Quote $quote */

            $quote = $this->sessionManager->getQuote();
            $data = $this->getRequest()->getParams();
            $guestEmail = $data['guestEmail'];

            if (!$quote->getCustomerId()) {
                $quote->setCustomerEmail($guestEmail);
                $quote->getBillingAddress()->setEmail($guestEmail);
            }

            if (!$this->getRequest()->isPost()) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Wrong method.'));
            }

            if (!$quote || !$quote->getId()) {
                $this->logger->info('Unable to build Capture Context request');
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Something went wrong. Please try again.')
                );
            }
            $commandResult = $this->commandManager->executeByCode(
                self::COMMAND_CODE,
                $quote->getPayment()
            );

            $commandResult = $commandResult->get();
            
            $token = $commandResult['response'];
            if(isset($token['rmsg'])){
            if($token['rmsg']=="Authentication Failed"){
            $this->logger->info('Unable to load the Unified Checkout form.');
            throw new PaEnrolledException(
                __('Something went wrong. Please try again.'),
                401);
        }
    }
            $captureContext = json_decode($this->urlDecoder->decode(
                str_replace('_', '/', str_replace('-', '+', explode('.', string: $token)[1]))
            ));
            $data['captureContext'] = $commandResult;
            $data['unified_checkout_client_library'] = $captureContext->ctx[0]->data->clientLibrary;

            $this->quoteRepository->save($quote);

            $result->setData(
                [
                    'success' => true,
                    'captureContext' => $commandResult['response'],
                    'unified_checkout_client_library' => $data['unified_checkout_client_library'],
                    'layoutSelected' => $this->config->getUcLayout(),
                    'setupcall' => $this->config->isPayerAuthEnabled()
                ]
            );
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ['exception' => $e]);
            $this->logger->info('Unable to build Capture Context.');
            $result->setData(['error_msg' => __('Something went wrong. Please try again.')]);
        }

        return $result;
    }
}
