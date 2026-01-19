<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model\Logger;

class Censor
{
    /**
     * @var string
     */
    private const CENSOR_MASK = '**MASKED**';

    /**
     * @var string
     */
    private const CENSOR_MASK_PAN = 'xxxx';

    /**
     * @var string
     */
    private const CENSOR_MASK_OBJECT = '**MASKED_OBJECT**';

    /**
     * @var array
     */
    private $sensitiveFields = [
        'accountNumber',
        'cardAccountNumber',
        'bankTransitNumber',
        'card_cvn',
        'cvNumber',
        'expirationMonth',
        'expirationYear',
    ];

    /**
     * @var array
     */
    private $immutableFields = [
        'orderID',
        'requestID',
        'sessionsRequestID',
        'deviceFingerprintID',
        'merchantReferenceCode',
        'paymentNetworkTransactionID',
        'creationTimeStamp',
        'phoneNumber',
        'reasonCode',
        'transaction_id'
    ];

    /**
     * @var array
     */
    private $PANRegExps = [
        '/4\\p{N}{3}([\\ \\-]?)\\p{N}{4}\\1\\p{N}{4}\\1\\p{N}{4}/u',
        '/4\\p{N}{3}([\\ \\-]?)(?:\\p{N}{4}\\1){2}\\p{N}(?:\\p{N}{3})?/u',
        '/5[1-5]\\p{N}{2}([\\ \\-]?)\\p{N}{4}\\1\\p{N}{4}\\1\\p{N}{4}/u',
        '/6(?:011|22(?:1(?=[\\ \\-]?(?:2[6-9]|[3-9]))|[2-8]|9(?=[\\ \\-]?(?:[01]|2[0-5])))|4[4-9]\\p{N}|5\\p{N}\\p{N})([\\ \\-]?)\\p{N}{4}\\1\\p{N}{4}\\1\\p{N}{4}/u',
        '/35(?:2[89]|[3-8]\\p{N})([\\ \\-]?)\\p{N}{4}\\1\\p{N}{4}\\1\\p{N}{4}/u',
        '/3[47]\\p{N}\\p{N}([\\ \\-]?)\\p{N}{6}\\1\\p{N}{5}/u',
    ];

    /**
     * Strips sensitive data from input
     *
     * @param mixed $value
     * @return mixed
     */
    public function censor($value)
    {
        if (is_array($value)) {
            $value = (array) json_decode(json_encode($value), true);
            array_walk_recursive($value, [$this, 'censorArrayItem']);
            return $value;
        }

        if (is_string($value)) {
            return $this->censorString($value);
        }

        if ((!is_object($value) && settype($value, 'string') !== false)
            || is_object($value) && method_exists($value, '__toString')
        ) {
            return $this->censorString((string)$value);
        }

        //we have no idea how to clear other types of data so returning empty string
        return '';
    }

    /**
     * Censors single array item
     *
     * @param mixed $value
     * @param string $index
     */
    private function censorArrayItem(&$value, $index)
    {
        if (in_array($index, $this->immutableFields)) {
            return;
        }

        if (in_array($index, $this->sensitiveFields)) {
            $value = self::CENSOR_MASK;
            return;
        };

        if (is_string($value)) {
            $value = $this->censorString($value);
            return;
        }

        if ((!is_object($value) && settype($value, 'string') !== false)
            || is_object($value) && method_exists($value, '__toString')
        ) {
            $value = $this->censorString((string)$value);
            return;
        }

        // we don't know anything about internal structure so just masking that
        if (is_object($value)) {
            $value = self::CENSOR_MASK_OBJECT;
            return;
        }

        $value = '';
    }

    /**
     * Censors input string
     *
     * @param string $value
     * @return string
     */
    private function censorString($value)
    {
        return (string) preg_replace($this->PANRegExps, self::CENSOR_MASK_PAN, $value);
    }
}
