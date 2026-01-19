<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway;

class PaEnrolledException extends \Magento\Framework\Webapi\Exception
{
    /**
     * @param \Magento\Framework\Phrase $phrase
     * @param int $reasonCode
     * @param int $httpCode
     * @param array $details
     * @param string $name
     * @param array $errors
     * @param string $stackTrace
     */
    public function __construct(
        \Magento\Framework\Phrase $phrase,
        $reasonCode,
        int $httpCode = self::HTTP_BAD_REQUEST,
        array $details = [],
        string $name = '',
        ?array $errors = null,
        ?string $stackTrace = null
    ) {
        parent::__construct($phrase, $reasonCode, $httpCode, $details, $name, $errors, $stackTrace);
    }
}
