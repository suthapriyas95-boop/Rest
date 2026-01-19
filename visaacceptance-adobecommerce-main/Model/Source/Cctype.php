<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model\Source;

class Cctype extends \Magento\Payment\Model\Source\Cctype
{
    /**
     * Retrieve available credit card types
     *
     * @return array
     */
    public function getAllowedTypes()
    {
        return ['VI', 'MC', 'AE', 'DI', 'JCB', 'DN', 'JW'];
    }

    /**
     * Returns credit card types as option array for configuration
     *
     * @return array
     */
    public function toOptionArray()
    {
        $allowed = $this->getAllowedTypes();
        $options = [];

        foreach ($this->_paymentConfig->getCcTypes() as $code => $name) {
            if (in_array($code, $allowed)) {
                $options[] = ['value' => $code, 'label' => $name];
            }
        }

        // Add JW (JAYWAN) card type if not present in default Magento card types
        if (in_array('JW', $allowed)) {
            $jwExists = false;
            foreach ($options as $option) {
                if ($option['value'] === 'JW') {
                    $jwExists = true;
                    break;
                }
            }
            if (!$jwExists) {
                $options[] = ['value' => 'JW', 'label' => 'Jaywan'];
            }
        }

        return $options;
    }
}
 