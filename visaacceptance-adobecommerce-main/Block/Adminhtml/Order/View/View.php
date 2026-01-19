<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Block\Adminhtml\Order\View;

use CyberSource\Payment\Helper\Data;

/**
 * Payer Authentication block
 * Class Info
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class View extends \Magento\Sales\Block\Adminhtml\Order\AbstractOrder
{
    /** @var Data */
    private $helper;

    /**
     * View constructor.
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
        array $data
    ) {
        parent::__construct($context, $registry, $adminHelper, $data);

        $this->helper = $helper;
    }

    /**
     * Retrieves additional information from the payment additional information.
     *
     * @return array The additional information, with keys formatted as readable strings.
     */
    public function getAdditionalInformation()
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
    public function getPayerAuthenticationAdditionalInformation()
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
     * @param mixed $value The value to render.
     * @param bool $escapeHtml Whether to escape HTML. Defaults to false.
     * @return array The rendered value as an array.
     */
    public function getValueAsArray($value, $escapeHtml = false)
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
     * @param string $key The key to build a readable key from.
     * @return string The readable key.
     */
    private function buildReadableKey($key)
    {
        $key = implode(" ", preg_split('/(?=[A-Z][a-z]+)/', $key));
        $key = str_replace("_", " ", $key);
        return ucwords($key);
    }
}
