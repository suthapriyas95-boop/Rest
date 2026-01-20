<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Block\Adminhtml\Order\View;

use CyberSource\Payment\Helper\Data;

/**
 * Admin order view block for CyberSource payment details.
 */
class Info extends \Magento\Sales\Block\Adminhtml\Order\AbstractOrder
{
    /** @var Data */
    private $helper;

    /**
     * Info constructor.
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Sales\Helper\Admin $adminHelper
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Sales\Helper\Admin $adminHelper,
        Data $helper,
        array $data = []
    ) {
        parent::__construct($context, $registry, $adminHelper, $data);
        $this->helper = $helper;
    }

    /**
     * Retrieves additional information from the payment additional information.
     *
     * @return array
     */
    public function getAdditionalInformation(): array
    {
        $additionalInformation = $this->getOrder()->getPayment()->getAdditionalInformation();

        $response = [];
        if (!empty($additionalInformation)) {
            $data = $this->helper->getAdditionalData($additionalInformation);
            foreach ($data as $key => $value) {
                $response[$this->buildReadableKey($key)] = $value;
            }
        }

        return $response;
    }

    /**
     * Retrieves payer authentication additional information from the payment.
     *
     * @return array
     */
    public function getPayerAuthenticationAdditionalInformation(): array
    {
        $additionalInformation = $this->getOrder()->getPayment()->getAdditionalInformation();

        $response = [];
        if (!empty($additionalInformation)) {
            $payerAuthData = $this->helper->getPayerAuthenticationData($additionalInformation);
            foreach ($payerAuthData as $key => $value) {
                $response[$this->buildReadableKey($key)] = $value;
            }
        }

        return $response;
    }

    /**
     * Renders the value as an array.
     *
     * @param mixed $value
     * @param bool $escapeHtml
     * @return array
     */
    public function getValueAsArray($value, bool $escapeHtml = false): array
    {
        if (empty($value)) {
            return [];
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        if ($escapeHtml) {
            foreach ($value as $_key => $_val) {
                $value[$_key] = $this->escapeHtml($_val);
            }
        }

        return $value;
    }

    /**
     * Builds a readable key from the given key.
     *
     * @param string $key
     * @return string
     */
    private function buildReadableKey(string $key): string
    {
        $key = implode(' ', preg_split('/(?=[A-Z][a-z]+)/', $key));
        $key = str_replace('_', ' ', $key);
        return ucwords($key);
    }
}
