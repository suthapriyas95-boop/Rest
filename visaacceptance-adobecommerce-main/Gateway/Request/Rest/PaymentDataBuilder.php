<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

use Magento\Payment\Helper\Formatter;
use Magento\Store\Model\StoreManagerInterface;

class PaymentDataBuilder implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    use Formatter;

    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        StoreManagerInterface $storeManager
    ) {
        $this->subjectReader = $subjectReader;
        $this->storeManager = $storeManager;
    }

    /**
     * Builds Order Data
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $request = [];

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $order = $paymentDO->getOrder();
        $storeId = $order->getStoreId();
        $this->storeManager->setCurrentStore($storeId);

        $request['clientReferenceInformation'] = ['code' => $order->getOrderIncrementId()];

        try {
            $amount = $this->subjectReader->readAmount($buildSubject);
        } catch (\InvalidArgumentException $e) {
            // seems we are doing authorization reversal, getting a full authorized amount
            $amount = $paymentDO->getPayment()->getBaseAmountAuthorized();
        }

        $request['orderInformation'] = [
            'amountDetails' => [
                'currency' => $paymentDO->getOrder()->getCurrencyCode(),
                'totalAmount' => $this->formatPrice($amount),
            ],
        ];

        return $request;
    }
}
