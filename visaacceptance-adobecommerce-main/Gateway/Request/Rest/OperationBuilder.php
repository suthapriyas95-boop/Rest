<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

class OperationBuilder implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    /**
     * @var string
     */
    private $operationName;

    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    /**
     * OperationBuilder constructor.
     *
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param string $operationName
     */
    public function __construct(
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        $operationName
    ) {
        $this->subjectReader = $subjectReader;
        $this->operationName = $operationName;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $payment->getOrder();
        $invoices = $order->getInvoiceCollection();
        $creditmemos = $order->getCreditmemosCollection();
 
        if ($this->operationName === 'voids') {
        if ($invoices->getSize() == 0 || $creditmemos->getSize() == 0) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You cannot void this payment through visa acceptance payment gateway.')
                );
            }
        }
        if ($this->operationName === 'reversals') {
            if ($invoices->getSize() > 0 || $creditmemos->getSize() > 0) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('You cannot cancel this payment through visa acceptance payment gateway.')
                    );
                }
            }

        $id = $payment->getParentTransactionId() ?: $payment->getRefundTransactionId();
        return [
            \CyberSource\Payment\Gateway\Http\Client\Rest::KEY_URL_PARAMS => [
                $id,
                $this->operationName
            ]
        ];
    }
}
