<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class BillingTypes implements ArrayInterface
{
    /**
     * Return array of options as value-label pairs.
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'NONE', 'label' => 'None'],
            ['value' => 'FULL', 'label' => 'Full'],
            ['value' => 'PARTIAL', 'label' => 'Partial'],
        ];
    }
}
