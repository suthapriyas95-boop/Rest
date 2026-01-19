<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model\Quote\Payment;

use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Quote\Model\Quote\Payment;

class ToOrderPaymentPlugin
{
    /**
     * @var OrderPaymentExtensionInterfaceFactory
     */
    private $orderPaymentExtensionFactory;

    /**
     * ToOrderPaymentPlugin constructor.
     * @param OrderPaymentExtensionInterfaceFactory $orderPaymentExtensionFactory
     */
    public function __construct(
        OrderPaymentExtensionInterfaceFactory $orderPaymentExtensionFactory
    ) {
        $this->orderPaymentExtensionFactory = $orderPaymentExtensionFactory;
    }

    /**
     * Plugin method that converts CcaResponse extension attribute from Quote Payment to Order Payment model
     *
     * @param \Magento\Quote\Model\Quote\Payment\ToOrderPayment $subject
     * @param OrderPaymentInterface $result
     * @param Payment $quotePayment
     * @param array $data
     * @return OrderPaymentInterface
     */
    public function afterConvert(
        \Magento\Quote\Model\Quote\Payment\ToOrderPayment $subject,
        OrderPaymentInterface $result,
        Payment $quotePayment,
        $data = []
    ) {
        $this->assignSessionId($quotePayment, $result);
        $this->assignExtensionAttributes($quotePayment, $result);
        return $result;
    }

    /**
     * Assign payer_auth_enroll_session_id to order payment additional information
     *
     * @param Payment $quotePayment
     * @param OrderPaymentInterface $result
     */
    private function assignSessionId(
        Payment $quotePayment,
        OrderPaymentInterface $result
    ) {
        if ($sessionId = $quotePayment->getAdditionalInformation('payer_auth_enroll_session_id')) {
            $result->setAdditionalInformation('payer_auth_enroll_session_id', $sessionId);
        }
    }

    /**
     * Assign CcaResponse to order payment extension attributes
     *
     * @param Payment $quotePayment
     * @param OrderPaymentInterface $result
     */
    private function assignExtensionAttributes(
        Payment $quotePayment,
        OrderPaymentInterface $result
    ) {
        if ($quotePayment->getExtensionAttributes() === null) {
            return $result;
        }

        if ($ccaResponse = $quotePayment->getExtensionAttributes()->getCcaResponse()) {
            $orderPaymentExtensionAttributes = $this->getExtensionAttributes($result);
            $orderPaymentExtensionAttributes->setCcaResponse($ccaResponse);
        }
    }

    /**
     * Get Order Payment Extension Attributes
     *
     * @param OrderPaymentInterface $payment
     * @return OrderPaymentExtensionInterface
     */
    private function getExtensionAttributes(OrderPaymentInterface $payment)
    {
        $extensionAttributes = $payment->getExtensionAttributes();
        if (null === $extensionAttributes) {
            $extensionAttributes = $this->orderPaymentExtensionFactory->create();
            $payment->setExtensionAttributes($extensionAttributes);
        }
        return $extensionAttributes;
    }
}
