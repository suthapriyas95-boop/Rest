<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Validator;

use CyberSource\Payment\Model\Logger;
use CyberSource\Payment\Gateway\PaEnrolledException;
use CyberSource\Payment\Model\Config;

class PaEnrolledValidator extends \Magento\Payment\Gateway\Validator\AbstractValidator
{
    public const KEY_ACS_URL = 'acsUrl';
    public const KEY_ACESS_TOKEN = 'accessToken';
    public const KEY_STEPUP_URL = 'stepUpUrl';
    public const KEY_PA_REQ = 'pareq';
    public const KEY_PAYER_AUTH_ENROLL_REPLY = 'consumerAuthenticationInformation';
    public const KEY_AUTHENTICATION_TRANSACTION_ID = 'authenticationTransactionId';
    public const KEY_SCA_REQUIRED = 'strong_customer_authentication_required';
    public const KEY_PA_PENDING_AUTHENTICATION = 'PENDING_AUTHENTICATION';
    public const KEY_DECLINED = 'DECLINED';
    public const KEY_CUSTOMER_AUTHENTICATION_REQUIRED  = 'CUSTOMER_AUTHENTICATION_REQUIRED';
    public const KEY_CUSTOMER_AUTHENTICATION_FAILED = 'CONSUMER_AUTHENTICATION_FAILED';
    public const KEY_RESPONSE_CODE = 201;
    public const KEY_WEB_API_REST = 'webapi_rest';
    public const KEY_PAYER_AUTH_AUTHENTICATION_TRANSACTIONID = 'authenticationTransactionId';
    public const KEY_PAYER_AUTH_SIGNED_PARES = 'signedPares';
    public const KEY_PAYER_AUTH_ENROLL_TRANSACTION_ID = 'payer_auth_enroll_transaction_id';
    public const SET_UP = 'payerauthSetup';
    public const PAYER_AUTH_SANDBOX_URL = 'https://centinelapistag.cardinalcommerce.com';
    public const PAYER_AUTH_PROD_URL = 'https://centinelapi.cardinalcommerce.com';

    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $session;

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    /**
     * @var string
     */
    private $areaCode;

    /**
     * @var \CyberSource\Payment\Model\Logger
     */
    private $logger;

    /**
     * @var \Magento\Framework\Session\StorageInterface
     */
    private $sessionStorage;

    /**
     * @var \CyberSource\Payment\Model\Config
     */
    private $config;

     /**
      * @var \Magento\Payment\Gateway\Command\CommandManagerInterface
      */
    private $commandManager;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private $resultJsonFactory;

    /**
     * PaEnrolledValidator constructor.
     *
     * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param \Magento\Checkout\Model\Session $session
     * @param Config $config
     * @param Logger $logger
     * @param \Magento\Framework\App\State $state
     * @param \Magento\Framework\Session\StorageInterface $sessionStorage
     * @param \Magento\Payment\Gateway\Command\CommandManagerInterface $commandManager
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
    public function __construct(
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory,
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        \Magento\Checkout\Model\Session $session,
        Config $config,
        Logger $logger,
        \Magento\Framework\App\State $state,
        \Magento\Framework\Session\StorageInterface $sessionStorage,
        \Magento\Payment\Gateway\Command\CommandManagerInterface $commandManager,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        parent::__construct($resultFactory);
        $this->subjectReader = $subjectReader;
        $this->session = $session;
        $this->config = $config;
        $this->logger = $logger;
        $this->state = $state;
        $this->sessionStorage = $sessionStorage;
        $this->commandManager = $commandManager;
        $this->cartRepository = $cartRepository;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * Handles response code 475 for PA enrolled cards
     *
     * @param array $validationSubject
     * @return \Magento\Payment\Gateway\Validator\ResultInterface
     * @throws PaEnrolledException
     */
    public function validate(array $validationSubject)
    {
        $result = $this->resultJsonFactory->create();
        $response = $this->subjectReader->readResponse($validationSubject);
        $areaCode = $this->getArea();

        if ($areaCode == self::KEY_WEB_API_REST) {
            $quote = $this->session->getQuote();
            if (!$quote || !$quote->getId()) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Unable to load cart data.'));
            }
            $payment = $quote->getPayment();
        }
        $quote = $this->session->getQuote();
        $payment = $quote->getPayment();

        if (isset($response['consumerAuthenticationInformation']['acsUrl'])) {
            $acsUrl = $response['consumerAuthenticationInformation']['acsUrl'];
            $payment->setAdditionalInformation('acsUrl', $acsUrl);
        }

        if (isset($response['consumerAuthenticationInformation']['pareq'])) {
            $signedPares = $response['consumerAuthenticationInformation']['pareq'];
            $this->sessionStorage->setData('signedPares', $signedPares);
            $payment->setAdditionalInformation(self::KEY_PAYER_AUTH_SIGNED_PARES, $signedPares);
        }

        if (isset($response['consumerAuthenticationInformation']['authenticationTransactionId'])) {
            $authenticationTransactionId =
                $response['consumerAuthenticationInformation']['authenticationTransactionId'];
            $this->sessionStorage->setData('authenticationTransactionId', $authenticationTransactionId);
            $payment->setAdditionalInformation(
                self::KEY_PAYER_AUTH_AUTHENTICATION_TRANSACTIONID,
                $authenticationTransactionId
            );
        }

        $status = !empty($response['status']) ? $response['status'] : null;
        $reason = !empty($response['errorInformation']['reason']) ? $response['errorInformation']['reason'] : null;

        if ($status !== self::KEY_RESPONSE_CODE &&
            $status !== self::KEY_PA_PENDING_AUTHENTICATION &&
            $status !== self::KEY_DECLINED &&
            $reason !== self::KEY_CUSTOMER_AUTHENTICATION_REQUIRED
        ) {
            return $this->createResult(true);
        }

        $paException = null;

        if ($status == self::KEY_PA_PENDING_AUTHENTICATION) {
            $payerAuthEnrollReply = (array) $response[self::KEY_PAYER_AUTH_ENROLL_REPLY];
            if (isset($response['strong_customer_authentication_required']) &&
                $response['strong_customer_authentication_required'] === 1
            ) {
                $authendication = self::KEY_SCA_REQUIRED;
            } else {
                $authendication = self::KEY_PA_PENDING_AUTHENTICATION;
            }
            $this->logger->info('Payer Authentication is required.');
            $paException = new PaEnrolledException(
                __('Something went wrong. Please try again.'),
                $status,
                PaEnrolledException::HTTP_BAD_REQUEST,
                [
                    'code' => $authendication,
                    'cca' => [
                        'AcsUrl' => $payerAuthEnrollReply[self::KEY_ACS_URL],
                        'Payload' => $payerAuthEnrollReply[self::KEY_PA_REQ],
                        'accessToken' => $payerAuthEnrollReply[self::KEY_ACESS_TOKEN],
                        'stepUpUrl' => $payerAuthEnrollReply[self::KEY_STEPUP_URL],
                    ],
                    'order' => array_replace_recursive(
                        [
                            'OrderDetails' => [
                                'TransactionId' => $payerAuthEnrollReply[self::KEY_AUTHENTICATION_TRANSACTION_ID]
                            ]
                        ]
                    ),
                ]
            );
            } elseif ($payment->getAdditionalInformation('is_active_payment_token_enabler') &&
            $reason == self::KEY_CUSTOMER_AUTHENTICATION_REQUIRED &&
            ($this->config->isScaEnforcedOnCardSave() == "1")
            )  {
            $this->sessionStorage->setData(self::KEY_SCA_REQUIRED, false);
            $paException = new PaEnrolledException(
                __('Unable to complete your order at this point in time. Please try again later.'),
                10000,
                PaEnrolledException::HTTP_BAD_REQUEST
            );
        } elseif ($this->sessionStorage->getData(self::KEY_SCA_REQUIRED)) {
            $this->sessionStorage->setData(self::KEY_SCA_REQUIRED, false);
            $paException = new PaEnrolledException(
                __('Unable to complete your order at this point in time. Please try again later.'),
                10000,
                PaEnrolledException::HTTP_BAD_REQUEST
            );
        } elseif ($reason == self::KEY_CUSTOMER_AUTHENTICATION_REQUIRED) {
            if ($this->config->isPayerAuthEnabled() == "1") {
                $this->sessionStorage->setData(self::KEY_SCA_REQUIRED, true);
                $payment = $quote->getPayment();
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
                $this->logger->info('Strong Customer Authentication is required.');
                $paException = new PaEnrolledException(
                    __('Something went wrong. Please try again.'),
                    $reason,
                    PaEnrolledException::HTTP_BAD_REQUEST
                );
            } else {
                $this->logger->info('Strong Customer Authentication is required.');
                $paException = new PaEnrolledException(
                    __('Something went wrong. Please try again.'),
                    $reason,
                    PaEnrolledException::HTTP_BAD_REQUEST
                );
            }
        } 
        elseif ($status == self::KEY_DECLINED) {
            $payment->setAdditionalInformation(self::KEY_DECLINED, true);
                $paException = new PaEnrolledException(
                    __('Transaction has been declined.Please try again later.'),
                    $reason,
                    PaEnrolledException::HTTP_BAD_REQUEST
                );
           }
         
        if ($paException) {
            throw $paException;
        }
    }

    /**
     * Get area code
     *
     * @return string
     */
    public function getArea()
    {
        return $this->state->getAreaCode();
    }
}
