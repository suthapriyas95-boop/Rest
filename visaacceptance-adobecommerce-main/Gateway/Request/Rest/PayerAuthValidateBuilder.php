<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

use CyberSource\Payment\Model\Config;

class PayerAuthValidateBuilder implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    public const KEY_VALIDATE_CONSUMER_AUTHENTICATION = 'VALIDATE_CONSUMER_AUTHENTICATION';
    public const KEY_DECISION_SKIP = 'DECISION_SKIP';

    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    /**
     * @var \Magento\Framework\Session\StorageInterface
     */
    private $sessionStorage;

    /**
     *
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;
    
    /**
     * PayerAuthValidateBuilder constructor.
     *
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param \Magento\Framework\Session\StorageInterface $sessionStorage
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        \Magento\Framework\Session\StorageInterface $sessionStorage,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->subjectReader = $subjectReader;
        $this->sessionStorage = $sessionStorage;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     *
     * @return array
     */
    public function build(array $buildSubject)
    {

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $quote = $this->checkoutSession->getQuote();
        $signedPares = $this->sessionStorage->getData('signedPares');
        $authenticationTransactionId = $this->sessionStorage->getData('authenticationTransactionId');

        $requestArr = [
            'consumerAuthenticationInformation' => [
                'authenticationTransactionId' => $authenticationTransactionId,
                'signedPares' => $signedPares,
            ],
        ];

        if (!$payment->getAdditionalInformation(\Magento\Vault\Model\Ui\VaultConfigProvider::IS_ACTIVE_CODE)) {
            $requestArr['processingInformation'] =  [
                "actionList" => [self::KEY_VALIDATE_CONSUMER_AUTHENTICATION]

            ];
        } else {
            $requestArr['processingInformation'] = [
                "actionList" => [
                    self::KEY_VALIDATE_CONSUMER_AUTHENTICATION,
                    Config::KEY_TOKEN_CREATE
                ],
                "actionTokenTypes" => [
                    Config::KEY_PAYMENT_INSTRUMENT, Config::KEY_INSTRUMENT_IDENTIFIER, Config::KEY_CUSTOMER
                ]
            ];
        }
         $requestArr['processingInformation']['actionList'][] = self::KEY_DECISION_SKIP;

        return $requestArr;
    }
}
