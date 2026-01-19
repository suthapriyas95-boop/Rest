<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

class CaptureBuilder implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    /**
     * Builds ORDER request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        return [
            'processingInformation' => [
                'capture' => 'true',
            ]
        ];
    }
}
