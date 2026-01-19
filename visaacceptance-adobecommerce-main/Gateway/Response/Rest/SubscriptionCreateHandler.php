<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Response\Rest;

class SubscriptionCreateHandler implements \Magento\Payment\Gateway\Response\HandlerInterface
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
     * @var \CyberSource\Payment\Model\LoggerInterface
     */
    private $logger;

    /**
     * SubscriptionCreateHandler constructor.
     *
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param \CyberSource\Payment\Model\PaymentTokenManagement $paymentTokenManagement
     */
    public function __construct(
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        \CyberSource\Payment\Model\PaymentTokenManagement $paymentTokenManagement
    ) {
        $this->subjectReader = $subjectReader;
        $this->paymentTokenManagement = $paymentTokenManagement;
    }

    /**
     * @inheritDoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDo = $this->subjectReader->readPayment($handlingSubject);

        $paySubscriptionCreateReply = $response['tokenInformation']['paymentInstrument']['id'] ?? null;

        if (!$paySubscriptionCreateReply) {
            return;
        }

        if ($token = $response['tokenInformation']['customer']['id'] ?? null) {
            $this->paymentTokenManagement->storeTokenIntoPayment($paymentDo->getPayment(), $token);
        }

        if ($instrumentId = $response['tokenInformation']['instrumentIdentifier']['id'] ?? null) {
            $this->paymentTokenManagement->storeInstrumentIdIntoPayment($paymentDo->getPayment(), $instrumentId);
        }

        if ($paymentInstrumentId = $response['tokenInformation']['paymentInstrument']['id'] ?? null) {
            $this->paymentTokenManagement->storePaymentInstrumentIdIntoPayment(
                $paymentDo->getPayment(),
                $paymentInstrumentId
            );
        }
    }
}
