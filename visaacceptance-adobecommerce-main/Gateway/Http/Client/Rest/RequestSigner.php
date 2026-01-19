<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Http\Client\Rest;

use Laminas\Http\Request;
use Magento\Framework\Url\DecoderInterface;

class RequestSigner
{
    private const SIGNED_HEADERS_W_PAYLOAD = 'host date request-target digest v-c-merchant-id';
    private const SIGNED_HEADERS = 'host date request-target v-c-merchant-id';

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    private $dateTime;

    /**
     * @var \Magento\Framework\Url\DecoderInterface
     */
    protected $urlDecoder;

    /**
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param \Magento\Framework\Url\DecoderInterface $urlDecoder
     */
    public function __construct(
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        DecoderInterface $urlDecoder
    ) {
        $this->dateTime = $dateTime;
        $this->urlDecoder = $urlDecoder;
    }

    /**
     * Signer for rest request
     *
     * @param string $host
     * @param string $method
     * @param string $requestPath
     * @param string $payload
     * @param string $config
     * @param int|null $storeId
     * @param string $contentType
     *
     * @return array
     */
    public function getSignedHeaders(
        $host,
        $method,
        $requestPath,
        $payload,
        $config,
        $storeId = null,
        $contentType = 'application/json'
    ) {
        $date = $this->dateTime->gmtDate("D, d M Y H:i:s \G\M\T");

        $headers = [
            'Content-Type: ' . $contentType,
            'Date:' . $date,
            'Host:' . $host,
            'v-c-merchant-id: ' . $config->getMerchantId($storeId)
        ];

        $signatureValueArray = [
            'host: ' . $host,
            'date: ' . $date,
            'request-target: ' . strtolower($method) . ' ' . $requestPath
        ];

        if ($this->isPayloadExpected($method)) {
            $payloadDigest = $this->generateDigest($payload);
            $signatureValueArray[] = "digest: " . $payloadDigest;
            $headers[] = 'Digest: ' . $payloadDigest;
        }

        $signatureValueArray[] = 'v-c-merchant-id: ' . $config->getMerchantId($storeId);

        $signatureValue = base64_encode(
            hash_hmac(
                'sha256',
                mb_convert_encoding(
                    implode("\n", $signatureValueArray),
                    'UTF-8'
                ),
                $this->urlDecoder->decode($config->getRestKeyValue($storeId)),
                true
            )
        );

        $signedHeaders = $this->isPayloadExpected($method) ? self::SIGNED_HEADERS_W_PAYLOAD : self::SIGNED_HEADERS;

        $finalSignature = [
            "keyid=\"{$config->getRestKeyId($storeId)}\"",
            "algorithm=\"HmacSHA256\"",
            "headers=\"{$signedHeaders}\"",
            "signature=\"{$signatureValue}\""
        ];

        $headers[] = 'Signature: ' . implode(", ", $finalSignature);

        return $headers;
    }

    /**
     * GeneratevDigest
     *
     * @param string $payload
     * @return string
     */
    private function generateDigest($payload)
    {
        $digestHash = hash("sha256", mb_convert_encoding($payload, 'UTF-8'), true);
        return 'SHA-256=' . base64_encode($digestHash);
    }

    /**
     * Generate a digest of the payload for the signature.
     *
     * @param string $method
     * @return bool
     */
    private function isPayloadExpected($method)
    {
        return in_array(
            $method,
            [
                Request::METHOD_PUT,
                Request::METHOD_POST,
            ]
        );
    }
}
