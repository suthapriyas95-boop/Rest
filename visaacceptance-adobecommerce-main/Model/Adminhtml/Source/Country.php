<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

class Country implements ArrayInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'BR', 'label' => 'Brazil'],
            ['value' => 'CA', 'label' => 'Canada'],
            ['value' => 'CL', 'label' => 'Chile'],
            ['value' => 'CN', 'label' => 'Mainland China'],
            ['value' => 'CO', 'label' => 'Colombia'],
            ['value' => 'CZ', 'label' => 'Czech Republic'],
            ['value' => 'DE', 'label' => 'Germany'],
            ['value' => 'DK', 'label' => 'Denmark'],
            ['value' => 'ES', 'label' => 'Spain'],
            ['value' => 'FI', 'label' => 'Finland'],
            ['value' => 'FR', 'label' => 'France'],
            ['value' => 'GB', 'label' => 'United Kingdom of Great Britain and Northern Ireland'],
            ['value' => 'GR', 'label' => 'Greece'],
            ['value' => 'HK', 'label' => 'Hong Kong'],
            ['value' => 'HR', 'label' => 'Croatia'],
            ['value' => 'HU', 'label' => 'Hungary'],
            ['value' => 'ID', 'label' => 'Indonesia'],
            ['value' => 'IE', 'label' => 'Ireland'],
            ['value' => 'IL', 'label' => 'Israel'],
            ['value' => 'IT', 'label' => 'Italy'],
            ['value' => 'JP', 'label' => 'Japan'],
            ['value' => 'KH', 'label' => 'Cambodia'],
            ['value' => 'KR', 'label' => 'Korea'],
            ['value' => 'LA', 'label' => 'Laos'],
            ['value' => 'MO', 'label' => 'Mongolia'],
            ['value' => 'MX', 'label' => 'Mexico'],
            ['value' => 'MY', 'label' => 'Malaysia'],
            ['value' => 'NL', 'label' => 'Netherlands'],
            ['value' => 'NO', 'label' => 'Norway'],
            ['value' => 'NZ', 'label' => 'New Zealand'],
            ['value' => 'PE', 'label' => 'Peru'],
            ['value' => 'PH', 'label' => 'Philippines'],
            ['value' => 'PL', 'label' => 'Poland'],
            ['value' => 'RU', 'label' => 'Russia'],
            ['value' => 'SE', 'label' => 'Sweden'],
            ['value' => 'SG', 'label' => 'Singapore'],
            ['value' => 'SK', 'label' => 'Slovakia'],
            ['value' => 'TH', 'label' => 'Thailand'],
            ['value' => 'TR', 'label' => 'Turkey'],
            ['value' => 'TW', 'label' => 'Taiwan'],
            ['value' => 'US', 'label' => 'United States of America'],
            ['value' => 'VN', 'label' => 'Vietnam'],
        ];
    }
}
