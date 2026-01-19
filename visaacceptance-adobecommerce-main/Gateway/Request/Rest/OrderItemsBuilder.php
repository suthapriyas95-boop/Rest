<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

use Magento\Payment\Gateway\Helper\Formatter;
use Magento\Payment\Gateway\Request\BuilderInterface;
use CyberSource\Payment\Gateway\Helper\SubjectReader;

/**
 * Class OrderItemsBuilder
 *
 * Builds order items for REST request
 */
class OrderItemsBuilder implements BuilderInterface
{
    use \Magento\Payment\Helper\Formatter;

    /**
     * @var string
     */
    private $objectName;

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * OrderItemsBuilder constructor.
     *
     * @param SubjectReader $subjectReader
     * @param string $objectName
     */
    public function __construct(
        SubjectReader $subjectReader,
        ?string $objectName = null
    ) {
        $this->subjectReader = $subjectReader;
        $this->objectName = $objectName;
    }

    /**
     * Builds order data
     *
     * @param array $buildSubject
     *
     * @return array
     */
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $result = $this->{'get' . ucfirst($this->objectName ?? '') . 'Items'}($payment);

        return $result;
    }

    /**
     * Get order items
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     */
    private function getOrderItems(\Magento\Payment\Model\InfoInterface $payment)
    {
        $result = $this->getItems($payment->getOrder()->getAllItems());

        if ($shippingItem = $this->getShippingOrderLineItem($payment)) {
            $result['orderInformation']['lineItems'][] = $shippingItem;
        }

        return $result;
    }

    /**
     * Get creditmemo items
     *
     * @param \Magento\Sales\Model\Order\Creditmemo $payment
     *
     * @return array
     */
    private function getCreditmemoItems(\Magento\Sales\Model\Order\Creditmemo $payment)
    {
        return $this->getItems($payment->getAllItems());
    }

    /**
     * Get invoice items
     *
     * @param \Magento\Sales\Model\Order\Invoice $payment
     *
     * @return array
     */
    private function getInvoiceItems(\Magento\Sales\Model\Order\Invoice $payment)
    {
        $invoice = $payment->getInvoice();
        if (!$invoice) {
            $invoice = $payment->getCreatedInvoice();
        }
        return $this->getItems($invoice->getAllItems());
    }

    /**
     * Get items
     *
     * @param array $items
     *
     * @return array
     */
    private function getItems(array $items)
    {
        $result = [];
        $i = 0;
        foreach ($items as $key => $item) {
            $type = $item->getProductType() ?: $item->getOrderItem()->getProductType();

            if ($this->shouldSkipItem($item)) {
                continue;
            }

            $qty = $item->getQty() ?: $item->getQtyOrdered();

            $price = $item->getBasePrice() - $item->getBaseDiscountAmount() / $qty;

            $result[] = [
                'productName' => $item->getName(),
                'productSKU' => $item->getSku(),
                'productCode' => $type,
                'quantity' => (int)$qty,
                'unitPrice' => $this->formatPrice($price),
                'taxAmount' => $this->formatPrice($item->getBaseTaxAmount())
            ];
        }
        return ['orderInformation' => ['lineItems' => $result]];
    }

    /**
     * Checks if item should be skipped in order items
     *
     * @param \Magento\Sales\Model\Order\Item $item
     *
     * @return bool
     */
    private function shouldSkipItem(\Magento\Sales\Model\Order\Item $item)
    {
        if ($item->getBasePrice() == 0) {
            return true;
        }

        /** @var \Magento\Sales\Model\Order\Item $orderItem */
        $orderItem = $item->getOrderItem() ?: $item;

        if ($orderItem->getProductType() == \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE) {
            if ($orderItem->getProductOptionByCode('product_calculations')
                == \Magento\Catalog\Model\Product\Type\AbstractType::CALCULATE_CHILD
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get order items
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     */
    private function getShippingOrderLineItem(\Magento\Payment\Model\InfoInterface $payment)
    {

        $amount = $payment->getData('base_shipping_amount');

        if (!$amount || $amount <= 0) {
            return [];
        }

        $shippingData = [
            'productCode' => 'shipping_and_handling',
            'quantity' => 1,
            'unitPrice' => $this->formatPrice($amount),
        ];

        if (!$payment instanceof \Magento\Sales\Model\Order\Payment) {
            return $shippingData;
        }

        $order = $payment->getOrder();
        $shippingData['taxAmount'] = $this->formatPrice($order->getBaseShippingTaxAmount());

        return $shippingData;
    }
}
