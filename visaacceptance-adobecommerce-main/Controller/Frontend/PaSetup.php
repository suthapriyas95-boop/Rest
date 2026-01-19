<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Controller\Frontend;

use CyberSource\Payment\Model\Ui\ConfigProvider;
use Magento\Quote\Api\Data\PaymentInterface;

class PaSetup extends \Magento\Framework\App\Action\Action
{
    public const COMMAND_CODE = 'payerauthSetup';

    public const PAYER_AUTH_SANDBOX_URL = 'https://centinelapistag.cardinalcommerce.com';
    public const PAYER_AUTH_PROD_URL = 'https://centinelapi.cardinalcommerce.com';
    /**
     * @var \Magento\Payment\Gateway\Command\CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private $jsonFactory;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $cartRepository;

      /**
       * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
       */
    private $subjectReader;

    /**
     * @var \Magento\Framework\Data\Form\FormKey\Validator
     */
    private $formKeyValidator;

    /**
     * @var \CyberSource\Payment\Model\LoggerInterface
     */
    private $logger;

     /**
      * @var \Magento\Framework\Session\StorageInterface
      */
    private $sessionStorage;

      /**
       * @var \Magento\Checkout\Model\Session
       */
    private $checkoutSession;
    /**
     * RequestToken constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Payment\Gateway\Command\CommandManagerInterface $commandManager
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator
     * @param \CyberSource\Payment\Model\LoggerInterface $logger
     * @param \Magento\Framework\Session\StorageInterface $sessionStorage
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Payment\Gateway\Command\CommandManagerInterface $commandManager,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator,
        \CyberSource\Payment\Model\LoggerInterface $logger,
        \Magento\Framework\Session\StorageInterface $sessionStorage,
        \Magento\Checkout\Model\Session $checkoutSession,
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
    ) {
        parent::__construct($context);
        $this->commandManager = $commandManager;
        $this->jsonFactory = $jsonFactory;
        $this->cartRepository = $cartRepository;
        $this->formKeyValidator = $formKeyValidator;
        $this->logger = $logger;
        $this->sessionStorage = $sessionStorage;
        $this->checkoutSession = $checkoutSession;
        $this->subjectReader = $subjectReader;
    }

    /**
     * Execute action based on request and return result
     *
     * Note: Request will be added as operation argument in future
     *
     * @return \Magento\Framework\Controller\ResultInterface|\Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {

        $resultJson = $this->jsonFactory->create();
        $quote = $this->checkoutSession->getQuote();
        try {
            if (!$this->getRequest()->isPost()) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Wrong method.'));
            }

            if (!$quote || !$quote->getId()) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Quote is not defined'));
            }

            $payment = $quote->getPayment();

            $browserDetails = $this->getRequest()->getParams();
            $this->sessionStorage->setData('browser_details', $browserDetails);

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

            $payment->importData($data);

            $tokenResult = $this->commandManager->executeByCode(
                self::COMMAND_CODE,
                $quote->getPayment()
            );

            $this->cartRepository->save($quote);

            $resultJson->setData(array_merge(
                ['success' => true],
                ['sandbox' => self::PAYER_AUTH_SANDBOX_URL],
                ['production' => self::PAYER_AUTH_PROD_URL],
                $tokenResult->get()
            ));
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $resultJson->setData(['success' => false, 'error_msg' => $e->getMessage()]);
            $this->logger->critical($e->getMessage(), ['exception' => $e]);
        } catch (\Exception $e) {
            $resultJson->setData(['success' => false, 'error_msg' => __('Unable to handle Setup request')]);
            $this->logger->critical($e->getMessage(), ['exception' => $e]);
        }

        return $resultJson;
    }
}
