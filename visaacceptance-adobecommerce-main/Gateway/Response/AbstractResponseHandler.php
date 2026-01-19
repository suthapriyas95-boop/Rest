<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Response;

use CyberSource\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Helper\ContextHelper;

abstract class AbstractResponseHandler
{
    private const TRANSACTION_ID = "transaction_id";
    private const REASON_CODE = "reason_code";
    private const DECISION = "decision";
    private const MERCHANT_REFERENCE_CODE = "req_reference_number";
    public const TOKEN_DATA = "token_data";
    private const CARD_NUMBER = 'req_card_number';
    private const REQUEST_ID = "requestID";

    private const ADDITIONAL_INFO_REQUEST_KEYS = [
        'auth_amount',
        'auth_avs_code',
        'auth_avs_code_raw',
        'auth_code',
        'auth_response',
        'auth_time',
        'auth_trans_ref_no',
        'decision_case_priority',
        'decision_early_rcode',
        'decision_early_reason_code',
        'decision_early_return_code',
        'decision_rcode',
        'decision_reason_code',
        'decision_return_code',
        'decision_rflag',
        'decision_rmsg',
        'message',
        'request_token',
        'score_address_info',
        'score_bin_country',
        'score_card_account_type',
        'score_card_issuer',
        'score_card_scheme',
        'score_factors',
        'score_host_severity',
        'score_identity_info',
        'score_model_used',
        'score_phone_info',
        'score_rcode',
        'score_reason_code',
        'score_return_code',
        'score_rflag',
        'score_rmsg',
        'score_score_result',
        'score_suspicious_info',
        'score_time_local',
    ];

    /**
     * @var SubjectReader
     */
    protected $subjectReader;

    /**
     * AbstractResponseHandler constructor.
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        SubjectReader $subjectReader
    ) {
        $this->subjectReader = $subjectReader;
    }

    /**
     * Retrieves a valid payment instance from the build subject.
     *
     * @param array $buildSubject The build subject.
     * @return \Magento\Payment\Model\InfoInterface The valid payment instance.
     * @throws \InvalidArgumentException If the payment instance is not found.
     */
    protected function getValidPaymentInstance(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObjectInterface $paymentDO */
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        /** @var \Magento\Payment\Model\InfoInterface $payment */
        $payment = $paymentDO->getPayment();

        ContextHelper::assertOrderPayment($payment);

        return $payment;
    }

    /**
     * Handles the authorize response.
     *
     * @param \Magento\Sales\Model\Order\Payment $payment The payment instance.
     * @param array $cyberSourceResponse The CyberSource response.
     * @return \Magento\Sales\Model\Order\Payment The updated payment instance.
     */
    protected function handleAuthorizeResponse($payment, $cyberSourceResponse)
    {
        /** @var $payment \Magento\Sales\Model\Order\Payment */
        $tokenData = $this->buildTokenToSave($cyberSourceResponse);

        $payment->setTransactionId($cyberSourceResponse[self::TRANSACTION_ID]);
        $payment->setCcTransId($cyberSourceResponse[self::TRANSACTION_ID]);
        $payment->setAdditionalInformation(self::TRANSACTION_ID, $cyberSourceResponse[self::TRANSACTION_ID]);
        $payment->setAdditionalInformation(self::REASON_CODE, $cyberSourceResponse[self::REASON_CODE]);
        $payment->setAdditionalInformation(self::DECISION, $cyberSourceResponse[self::DECISION]);
        $payment->setAdditionalInformation(
            self::MERCHANT_REFERENCE_CODE,
            $cyberSourceResponse[self::MERCHANT_REFERENCE_CODE]
        );

        $maskedPan = $cyberSourceResponse[self::CARD_NUMBER] ?? '';
        $payment->setAdditionalInformation(
            'cardNumber',
            substr($maskedPan, 0, 6) . str_repeat('x', strlen($maskedPan) - 10) . substr($maskedPan, -4)
        );
        $payment->setCcLast4(substr($maskedPan, -4));
        $payment->setAdditionalInformation('cardType', $cyberSourceResponse['req_card_type']);

        //pass other returned fields by list
        foreach (self::ADDITIONAL_INFO_REQUEST_KEYS as $responseKey) {
            if (!isset($cyberSourceResponse[$responseKey])) {
                continue;
            }
            $payment->setAdditionalInformation($responseKey, $cyberSourceResponse[$responseKey]);
        }

        if ($cyberSourceResponse['reason_code'] === "480") {
            $payment->setIsFraudDetected(true);
            $payment->setIsTransactionPending(true);
        }

        if ($tokenData !== null) {
            $payment->setAdditionalInformation(self::TOKEN_DATA, $tokenData);
        }

        // validate that requested amount matches order amount to avoid replay attacks
        if ($payment->getBaseAmountOrdered() != $cyberSourceResponse['req_amount']) {
            $payment->setIsFraudDetected(true);
        }

        return $payment;
    }

    /**
     * Builds token data to save.
     *
     * @param array $cyberSourceResponse The CyberSource response.
     * @return array Token data to save.
     */
    private function buildTokenToSave($cyberSourceResponse)
    {
        /**
         * Avoid building because payment was placed with token or was set to REVIEW by DM
         */
        if (!isset($cyberSourceResponse['payment_token'])) {
            return null;
        }

        $cardType = isset($cyberSourceResponse['req_card_type']) ? $cyberSourceResponse['req_card_type'] : '';
        $cardNumber = $cyberSourceResponse['req_card_number'] ?? '';
        $ccLastFour = substr($cardNumber, -4);
        $cardExpiry = isset($cyberSourceResponse['req_card_expiry_date'])
            ? $cyberSourceResponse['req_card_expiry_date']
            : '';

        $result = [
            'payment_token' => $cyberSourceResponse['payment_token'],
            'card_type' => $cardType,
            'cc_last4' => $ccLastFour,
            'card_expiry_date' => $cardExpiry,
            'instrument_id' => $cyberSourceResponse['payment_token_instrument_identifier_id'] ?? null,
        ];

        if (preg_match('/^([0-9]{6}).+/', $cardNumber, $matches)) {
            $result['card_bin'] = $matches[1];
        }

        return $result;
    }
}
