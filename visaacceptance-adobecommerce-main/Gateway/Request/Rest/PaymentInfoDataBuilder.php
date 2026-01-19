<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

class PaymentInfoDataBuilder implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    public const KEY_EXP_DATE = 'expDate';
    public const KEY_FLEX_MASKED_PAN = 'maskedPan';

    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    /**
     * @var \CyberSource\Payment\Model\Config
     */
    private $gatewayConfig;

    /**
     * @var \CyberSource\Payment\Helper\RequestDataBuilder
     */
    private $requestDataBuilder;

    /**
     * @var \CyberSource\Payment\Model\PaymentTokenManagement
     */
    private $paymentTokenManagement;

    /**
     * @var \CyberSource\Payment\Model\LoggerInterface
     */
    private $logger;

    /**
     * PaymentInfoDataBuilder constructor.
     *
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param \CyberSource\Payment\Model\Config $gatewayConfig
     * @param \CyberSource\Payment\Helper\RequestDataBuilder $requestDataBuilder
     * @param \CyberSource\Payment\Model\PaymentTokenManagement $paymentTokenManagement
     * @param \CyberSource\Payment\Model\LoggerInterface $logger
     */
    public function __construct(
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        \CyberSource\Payment\Model\Config $gatewayConfig,
        \CyberSource\Payment\Helper\RequestDataBuilder $requestDataBuilder,
        \CyberSource\Payment\Model\PaymentTokenManagement $paymentTokenManagement,
        \CyberSource\Payment\Model\LoggerInterface $logger
    ) {
        $this->subjectReader = $subjectReader;
        $this->gatewayConfig = $gatewayConfig;
        $this->requestDataBuilder = $requestDataBuilder;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->logger = $logger;
    }

    /**
     * Builds Merchant Data
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $request = [];

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $token = $this->paymentTokenManagement->getTokenFromPayment($payment);

        if (!empty($token)) {
            $request['paymentInformation'] =  [

                "customer" => [
                    'id' => $token,
                  ]
                ];
        }

        return $request;
    }
}
