<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Response\Rest;

class TransactionDetailsHandler implements \Magento\Payment\Gateway\Response\HandlerInterface
{
    private const KEY_TRANSACTION_ID = 'id';

    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    /**
     * @var bool
     */
    private $shouldCloseTransaction;

    /**
     * @var bool
     */
    private $shouldCloseParentTransaction;

    /**
     * TransactionDetailsHandler constructor.
     *
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param bool|null $shouldCloseTransaction
     * @param bool|null $shouldCloseParentTransaction
     */
    public function __construct(
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        ?bool $shouldCloseTransaction = null,
        ?bool $shouldCloseParentTransaction = null
    ) {
        $this->subjectReader = $subjectReader;
        $this->shouldCloseTransaction = $shouldCloseTransaction;
        $this->shouldCloseParentTransaction = $shouldCloseParentTransaction;
    }

    /**
     * Handles transaction details
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        if (!$payment instanceof \Magento\Sales\Model\Order\Payment) {
            return;
        }

        $payment->setTransactionId($response[self::KEY_TRANSACTION_ID]);
        $payment->setCcTransId($response[self::KEY_TRANSACTION_ID]);
        if (isset($response["processingInformation"]["paymentSolution"]) && $response["status"] == "AUTHORIZED") {
            $payment->setAdditionalInformation(
                'skipPaymentSolution',
                $response["processingInformation"]["paymentSolution"]
            );
        }
        if (isset($response['paymentAccountInformation']['card']['type'])) {
            $payment->setAdditionalInformation('cardType', $response['paymentAccountInformation']['card']['type']);
        }
        if ($this->shouldCloseTransaction() !== null) {
            $payment->setIsTransactionClosed($this->shouldCloseTransaction());
        }
        if ($this->shouldCloseParentTransaction() !== null) {
            $payment->setShouldCloseParentTransaction($this->shouldCloseParentTransaction());
        }
    }

    /**
     * Whether transaction should be closed
     *
     * @return bool
     */
    protected function shouldCloseTransaction()
    {
        return $this->shouldCloseTransaction;
    }

    /**
     * Whether parent transaction should be closed
     *
     * @return bool
     */
    protected function shouldCloseParentTransaction()
    {
        return $this->shouldCloseParentTransaction;
    }
}
