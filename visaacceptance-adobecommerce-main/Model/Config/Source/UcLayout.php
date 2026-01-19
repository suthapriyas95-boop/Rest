<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class UcLayout implements ArrayInterface
{
    public const SIDEBAR = 'SIDEBAR';

    public const EMBEDDED = 'EMBEDDED';
    public const ACTION_AUTHORIZE_CAPTURE = 'authorize_capture';

    /**
     * Return array of options as value-label pairs.
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::SIDEBAR ,
                'label' => __('Sidebar'),
            ],
            [
                'value' => self::EMBEDDED,
                'label' => __('Embedded'),
            ]
        ];
    }
}
