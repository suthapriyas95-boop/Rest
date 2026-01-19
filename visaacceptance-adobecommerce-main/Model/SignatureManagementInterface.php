<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model;

interface SignatureManagementInterface
{
    /**
     * Generates signature string for Secure Acceptance SOP and WM requests
     *
     * @param array $params
     * @param string $secretKey
     *
     * @return string
     */
    public function sign(array $params, string $secretKey);

    /**
     * Validates signature for Secure Acceptance SOP and WM requests
     *
     * @param array $response
     * @param string $key
     *
     * @return bool
     */
    public function validateSignature(array $response, string $key);
}
