<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\Model\Session\Quote as AdminQuoteSession;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Url\DecoderInterface;
use Magento\Payment\Gateway\Command\CommandManagerInterface;

class TransientDataRetrival extends Action
{
    public const COMMAND_CODE = 'get_token_detail';
    public const ADMIN_RESOURCE = 'Magento_Sales::sales';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var AdminQuoteSession
     */
    private $adminQuoteSession;

    /**
     * @var \Magento\Quote\Model\QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var \CyberSource\Payment\Model\LoggerInterface
     */
    private $logger;

    /**
     * @var DecoderInterface
     */
    private $urlDecoder;

    /**
     * TransientDataRetrival constructor.
     *
     * @param Action\Context $context
     * @param CommandManagerInterface $commandManager
     * @param JsonFactory $resultJsonFactory
     * @param AdminQuoteSession $adminQuoteSession
     * @param \Magento\Quote\Model\QuoteRepository $quoteRepository
     * @param \CyberSource\Payment\Model\LoggerInterface $logger
     * @param DecoderInterface $urlDecoder
     */
    public function __construct(
        Action\Context $context,
        CommandManagerInterface $commandManager,
        JsonFactory $resultJsonFactory,
        AdminQuoteSession $adminQuoteSession,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \CyberSource\Payment\Model\LoggerInterface $logger,
        DecoderInterface $urlDecoder
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->commandManager = $commandManager;
        $this->adminQuoteSession = $adminQuoteSession;
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
        $this->urlDecoder = $urlDecoder;
    }

    /**
     * Creates token detail request for admin order create.
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            if (!$this->getRequest()->isPost()) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Wrong method.'));
            }

            $data = $this->getRequest()->getPostValue('transientToken');
            if (!$data) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Missing token.'));
            }

            $quote = $this->adminQuoteSession->getQuote();
            if (!$quote || !$quote->getId()) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Unable to load admin quote.'));
            }

            $decodedToken = [];
            $tokenParts = explode('.', $data);
            if (isset($tokenParts[1])) {
                $decodedToken = json_decode($this->urlDecoder->decode($tokenParts[1]), true) ?? [];
            }

            $encodedToken = base64_encode($data);
            $quote->getPayment()->setAdditionalInformation('paymentToken', $encodedToken);

            if (isset($decodedToken['content']['processingInformation']['paymentSolution']['value'])) {
                $paymentSolution = $decodedToken['content']['processingInformation']['paymentSolution']['value'] ?? '';
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
            $quote->getPayment()->setAdditionalInformation('expDate', $expMonth && $expYear ? "$expMonth-$expYear" : null);

            $this->quoteRepository->save($quote);

            $result->setData([
                'success' => true,
                'cardType' => $cardType,
                'maskedPan' => $maskedPan,
                'expDate' => $expMonth && $expYear ? "$expMonth-$expYear" : null
            ]);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ['exception' => $e]);
            $result->setData([
                'status' => 500,
                'message' => $e->getMessage()
            ]);
        }

        return $result;
    }
}
