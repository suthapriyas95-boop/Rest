<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model\Config\Source;

class AllowedPayments implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Return array of allowed payments
     *
     * @return array
     */
    public function toOptionArray()
    {
        $optionArray = [
            [
                'value' => 'PANENTRY',
                'label' => 'Payment Card',
            ],
            [
                'value' => 'CLICKTOPAY',
                'label' => 'Click to Pay',
            ],
            [
                'value' => 'GOOGLEPAY',
                'label' => 'Google Pay',
            ],
            [
                'value' => 'APPLEPAY',
                'label' => 'Apple Pay',
             ]
        ];

        return $optionArray;
    }
}
