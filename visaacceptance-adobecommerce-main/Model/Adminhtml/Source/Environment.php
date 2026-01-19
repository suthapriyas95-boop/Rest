<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model\Adminhtml\Source;

class Environment implements \Magento\Framework\Option\ArrayInterface
{
    public const PRODUCTION = 'production';
    public const SANDBOX = 'sandbox';

    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::PRODUCTION,
                'label' => __('Production'),
            ],
            [
                'value' => self::SANDBOX,
                'label' => __('Sandbox'),
            ]
        ];
    }
}
