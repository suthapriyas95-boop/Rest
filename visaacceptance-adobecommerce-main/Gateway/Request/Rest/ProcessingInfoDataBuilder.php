<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

use CyberSource\Payment\Model\Config;

class ProcessingInfoDataBuilder implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    private const STORING_CARD = "storingcard";
    private const KEY_DECISION_SKIP = 'DECISION_SKIP';

    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;
    /**
     * @var \CyberSource\Payment\Model\Config
     */
    private $gatewayConfig;

    /**
     * Flag for processing type
     *
     * @var string
     */
    private string $flag;
    /**
     * ProcessingInfoDataBuilder constructor.
     *
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param \CyberSource\Payment\Model\Config $gatewayConfig
     * @param string $flag
     */
    public function __construct(
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        \CyberSource\Payment\Model\Config $gatewayConfig,
        string $flag
    ) {
        $this->subjectReader = $subjectReader;
        $this->gatewayConfig = $gatewayConfig;
        $this->flag          = $flag;
    }

    /**
     * Builds Merchant Data
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO      = $this->subjectReader->readPayment($buildSubject);
        $payment        = $paymentDO->getPayment();
        $request        = [];
        $additionalInfo = $payment->getAdditionalInformation(
            \Magento\Vault\Model\Ui\VaultConfigProvider::IS_ACTIVE_CODE
        );
        if (!$additionalInfo) {
            return $request;
        }

        if ($this->flag == self::STORING_CARD) {
            $request['processingInformation'] =  [
                "actionList"       => [Config::KEY_TOKEN_CREATE, self::KEY_DECISION_SKIP],
                "actionTokenTypes" => [
                    Config::KEY_PAYMENT_INSTRUMENT, Config::KEY_INSTRUMENT_IDENTIFIER, Config::KEY_CUSTOMER
                ]
            ];
        }

        return $request;
    }
}
