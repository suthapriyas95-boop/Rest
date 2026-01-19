<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model;

class SignatureManagement implements SignatureManagementInterface
{
    private const SHA_256 = 'sha256';

    /**
     * Builds the data to sign based on the signed field names
     *
     * @param array $params
     * @return string
     */
    private function buildDataToSign($params)
    {
        $signedFieldNames = explode(",", $params['signed_field_names'] ?? '');
        $dataToSign = [];
        foreach ($signedFieldNames as $field) {
            $dataToSign[] = $field . "=" . $params[$field];
        }
        return implode(",", $dataToSign);
    }

    /**
     * @inheritdoc
     */
    public function sign($params, $secretKey)
    {
        return base64_encode(hash_hmac(self::SHA_256, $this->buildDataToSign($params), $secretKey, true));
    }

    /**
     * @inheritdoc
     */
    public function validateSignature($response, $key)
    {
        if (!array_key_exists('signed_field_names', $response) || empty($response['signature'])) {
            return false;
        }
        $signature = $this->sign($response, $key);
        return hash_equals($signature, $response['signature']); // mitigating potential timing attack
    }
}
