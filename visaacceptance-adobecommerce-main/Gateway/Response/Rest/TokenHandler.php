<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Response\Rest;

const KEY_EXP_DATE = 'expDate';
class TokenHandler implements \Magento\Payment\Gateway\Response\HandlerInterface
{
    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    /**
     * @var \CyberSource\Payment\Model\PaymentTokenManagement
     */
    private $paymentTokenManagement;

    /**
     * @var \CyberSource\Payment\Model\Config
     */
    private $config;

    /**
     * @var \CyberSource\Payment\Model\LoggerInterface
     */
    private $logger;

    /**
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param \CyberSource\Payment\Model\PaymentTokenManagement $paymentTokenManagement
     * @param \CyberSource\Payment\Model\Config $config
     */
    public function __construct(
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        \CyberSource\Payment\Model\PaymentTokenManagement $paymentTokenManagement,
        \CyberSource\Payment\Model\Config $config
    ) {
        $this->subjectReader = $subjectReader;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->config = $config;
    }

    /**
     * Handles vault token data for microform instance
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        if (!$payment->getAdditionalInformation(\Magento\Vault\Model\Ui\VaultConfigProvider::IS_ACTIVE_CODE)) {
            return;
        }

        $tokenData = $this->buildTokenToSave($payment, $response);
        if ($tokenData !== null) {
            $payment->setAdditionalInformation(
                \CyberSource\Payment\Gateway\Response\AbstractResponseHandler::TOKEN_DATA,
                $tokenData
            );
        }
    }

    /**
     * Builds token data to save
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param array $response
     * @return array|null
     */
    private function buildTokenToSave($payment, $response)
    {
        $cardType = $response['paymentInformation']['card']['type'] ?? '';
        $cardNumber = $payment->getAdditionalInformation('maskedPan') ?? '';
        $ccLastFour = substr($cardNumber, -4);
        $cardExpiry = $payment->getAdditionalInformation(KEY_EXP_DATE);
        $cardBin = $response['paymentInformation']['tokenizedCard']['bin'] ?? '';

        $result = [
            'payment_token' => $this->paymentTokenManagement->getTokenFromPayment($payment),
            'card_type' => $cardType,
            'cc_last4' =>  $ccLastFour,
            'card_expiry_date' => $cardExpiry,
            'instrument_id' => $this->paymentTokenManagement->getInstrumentIdFromPayment($payment),
            'payment_instrument_id' => $this->paymentTokenManagement->getPaymentInstrumentIdIntoPayment($payment),
        ];

        return $result;
    }
}
