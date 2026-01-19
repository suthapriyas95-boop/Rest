<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

const KEY_EXP_DATE = 'expDate';

class CardDataBuilder implements \Magento\Payment\Gateway\Request\BuilderInterface
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
    public function build(array $buildSubject)
    {
        $paymentDo = $this->subjectReader->readPayment($buildSubject);

        $payment = $paymentDo->getPayment();

        $token = $this->paymentTokenManagement->getTokenFromPayment($payment);
        if (!empty($token)) {
            return [];
        }

        $result['paymentInformation']['card']['typeSelectionIndicator'] =
            \CyberSource\Payment\Helper\RequestDataBuilder::CARD_TYPE_SELECTION_INDICATOR_BY_CARDHOLDER;
        $expDate = $paymentDo
            ->getPayment()
            ->getAdditionalInformation(KEY_EXP_DATE);

        if (!$expDate) {
            return $result;
        }

        list($expMonth, $expYear) = array_pad(explode('-', $expDate ?? ''), 2, null);
        ;

        if ($expMonth) {
            $result['paymentInformation']['card']['expirationMonth'] = $expMonth;
        }

        if ($expYear) {
            $result['paymentInformation']['card']['expirationYear'] = $expYear;
        }

        return $result;
    }
}
