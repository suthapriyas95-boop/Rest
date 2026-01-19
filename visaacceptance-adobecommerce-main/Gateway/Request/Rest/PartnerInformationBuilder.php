<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

class PartnerInformationBuilder implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    private const KEY_DECISION_SKIP = 'DECISION_SKIP';

    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    /**
     * @var \CyberSource\Payment\Model\Config
     */
    private $config;

    /**
     *
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param \CyberSource\Payment\Model\Config $config
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        \CyberSource\Payment\Model\Config $config,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->subjectReader = $subjectReader;
        $this->config = $config;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $request = [
            'clientReferenceInformation' => [
                'partner' => [
                    'developerId' => '999',
                    'solutionId' => \CyberSource\Payment\Helper\AbstractDataBuilder::PARTNER_SOLUTION_ID,
                ]
            ],
        ];

        $request['processingInformation']['actionList'] = [self::KEY_DECISION_SKIP];
        $skipPaymentSolution = $payment->getAdditionalInformation("skipPaymentSolution");
        if ((isset($skipPaymentSolution))) {
           if ($skipPaymentSolution == "012") {
               $request['processingInformation']['paymentSolution'] = '012';
           } else if($skipPaymentSolution == "027"){
               $request['processingInformation']['paymentSolution'] = '027';
           }
       }

        return $request;
    }
}
