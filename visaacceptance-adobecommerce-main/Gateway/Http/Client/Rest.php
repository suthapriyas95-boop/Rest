<?php
/**
* Copyright © 2018 VisaAcceptance. All rights reserved.
* See accompanying LICENSE.txt for applicable terms of use and license.
*/
declare(strict_types=1);
 
namespace CyberSource\Payment\Gateway\Http\Client;
 
use Exception;
use CyberSource\Payment\Gateway\Http\Client\Rest\RequestSigner;
use CyberSource\Payment\Model\Config;
use CyberSource\Payment\Model\LoggerInterface;
use Laminas\Http\Client;
use Laminas\Http\Request;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Store\Model\StoreManagerInterface;
use CyberSource\Payment\Helper\Data;
 
class Rest implements ClientInterface
{    
    public const KEY_URL_PARAMS = 'url_params';
    public const CONTENT_TYPES_JSON = ['application/json', 'application/hal+json'];
    public const API_HOST_REST = 'api.visaacceptance.com';
    public const TEST_API_HOST_REST = 'apitest.visaacceptance.com';
 
    /** @var Config */
    private $config;
 
    /** @var LoggerInterface */
    private $logger;
 
    /** @var Client */
    private $client;
 
    /** @var Json */
    private $jsonSerializer;
 
    /** @var RequestSigner */
    private $requestSigner;
 
    /** @var string|null */
    private $requestPath;
 
    /** @var string|null */
    private $requestMethod;
 
    /** @var int|null */
    private $storeId = null;
 
    /** @var string */
    private $contentType;
 
    /** @var string */
    private $moduleConfigPath;
 
    /** @var StoreManagerInterface */
    private $storeManager;
    
 
    /**
     * @param Config $config
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param RequestSigner $requestSigner
     * @param Json $jsonSerializer
     * @param Client $httpClientFactory
     * @param int|null $storeId
     * @param string|null $requestPath
     * @param string|null $requestMethod
     * @param string $moduleConfigPath
     * @param string $contentType
     */
    public function __construct(
        Config $config,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        RequestSigner $requestSigner,
        Json $jsonSerializer,
        Client $httpClientFactory,
        ?int $storeId = null,
        ?string $requestPath = null,
        ?string $requestMethod = null,
        string $moduleConfigPath = '',
        string $contentType = 'application/json'
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->requestSigner = $requestSigner;
        $this->storeManager = $storeManager;
        $this->jsonSerializer = $jsonSerializer;
        $this->client = $httpClientFactory;
        $this->requestPath = $requestPath;
        $this->requestMethod = $requestMethod;
        $this->storeId = $storeId;
        $this->contentType = $contentType;
        $this->moduleConfigPath = $moduleConfigPath;
    }
 
    /**
     * Builds HTTP transfer object.
     *
     * @param TransferInterface $transferObject
     * @return array
     * @throws \InvalidArgumentException
     * @throws \GuzzleHttp\Exception\ClientException
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $this->config = $this->getConfigObject();
        $requestPath = '';
        $endpointUrl = '';
        $storeId = $this->storeManager->getStore()->getId();
 
        // Optional: log merchant for traceability
        $this->logger->info($this->config->getMerchantId($storeId));
 
        $requestMethod = $this->requestMethod ?: $transferObject->getMethod();
        if (!$this->isAcceptableMethod($requestMethod)) {
            throw new \InvalidArgumentException('method is unknown.');
        }
 
        $payload = $transferObject->getBody();
 
        // Build endpoint and payload, supporting URL params in either encrypted or plain body.
        if (isset($payload['encryptedRequest'])) {
            $headers = $transferObject->getHeaders();
            $urlParams = [];
            if (isset($headers['X-URL-Params'])) {
                $decoded = json_decode($headers['X-URL-Params'], true);
                if (is_array($decoded)) {
                    $urlParams = $decoded;
                }
            }
 
            $requestPath = $this->requestPath ?: $transferObject->getUri();
            $endpointUrl = 'https://' . $this->getApiHost() . $requestPath;
 
            if (!empty($urlParams)) {
                if (isset($urlParams[1]) && $urlParams[1] === '?') {
                    unset($urlParams[1]);
                    $urlPart = '?' . implode('?', $urlParams);
                } else {
                    $urlPart = '/' . implode('/', $urlParams);
                }
                $endpointUrl .= $urlPart;
                $requestPath .= $urlPart;
                unset($payload[static::KEY_URL_PARAMS]);
            }
        } else {
            $requestPath = $this->requestPath ?: $transferObject->getUri();
            $endpointUrl = 'https://' . $this->getApiHost() . $requestPath;
 
            if ($uriParams = $payload[static::KEY_URL_PARAMS] ?? null) {
                if (isset($uriParams[1]) && $uriParams[1] === '?') {
                    unset($uriParams[1]);
                    $urlPart = '?' . implode('?', $uriParams);
                } else {
                    $urlPart = '/' . implode('/', $uriParams);
                }
                $endpointUrl .= $urlPart;
                $requestPath .= $urlPart;
                unset($payload[static::KEY_URL_PARAMS]);
            }
        }
 
        $this->client->setMethod($requestMethod);
        $this->client->setUri($endpointUrl);

        // Temporary debug: if this is a payments request, log the raw payload
        // (unmasked) to help diagnose missing paymentInformation fields.
        if (strpos($requestPath, '/pts/v2/payments') !== false || strpos($endpointUrl, '/pts/v2/payments') !== false) {
            try {
                $this->logger->debug(['raw_request_unmasked' => $payload, 'endpoint' => $endpointUrl]);
            } catch (\Exception $e) {
                // swallow logging errors to avoid breaking payment flow
            }
        }
 
        // Keys to mask in both request and response logs
        $keysToMask = [
            // PII in request/response bodies
            'firstName', 'lastName', 'email', 'phoneNumber', 'postalCode',
            'address1', 'address2', 'street1', 'street2', 'city',
            'locality', 'company', 'ipAddress',
            // Card fields commonly present in response
            'number', 'expirationMonth', 'expirationYear', 'securityCode',
        ];
 
        // Mask the request payload for logging
        $maskedPayload = $this->maskSensitiveData(is_array($payload) ? $payload : [], $keysToMask);
 
        $log = [
            'client'   => static::class,
            'endpoint' => $endpointUrl,
            'method'   => $requestMethod,
            'request'  => $maskedPayload,
        ];
 
        if ($requestMethod == Request::METHOD_GET || $requestMethod == Request::METHOD_DELETE) {
            $this->client->setParameterGet($payload);
        } else {
            $payload = $this->jsonSerializer->serialize($payload);
            $this->client->setRawBody($payload);
        }
 
        // Ensure the client builds the final URI
        $this->client->getUri();
 
        // Sign request
        $signedHeaders = $this->requestSigner->getSignedHeaders(
            $this->getApiHost(),
            $requestMethod,
            $requestPath,
            $payload,
            $this->config,
            $storeId,
            $this->contentType
        );
        $this->client->setHeaders($signedHeaders);
 
        $response = [];
        try {
            $response = $this->client->send();
            $responseBody = $response->getBody();
 
            // Unwrap JSON body if possible
            try {
                $responseBody = $this->jsonSerializer->unserialize($responseBody);
            } catch (\InvalidArgumentException $e) {
                $responseBody = ['response' => $responseBody];
            }
 
            $response = array_merge(
                [
                    'http_code'    => $response->getStatusCode(),
                    'http_message' => $response->getReasonPhrase(),
                ],
                $responseBody
            );
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw $e;
        } finally {
            // Mask the response before logging
            $maskedResponse = $this->maskSensitiveData((array)$response, $keysToMask);
            $log['response'] = $maskedResponse;
 
            $this->logger->debug($log);
        }
 
        return $response;
    }
 
    /**
     * Sets request path.
     *
     * @param string $requestPath
     * @return Rest
     */
    public function setRequestPath($requestPath)
    {
        $this->requestPath = $requestPath;
        return $this;
    }
 
    /**
     * Set request method.
     *
     * @param string $requestMethod
     * @return Rest
     */
    public function setRequestMethod($requestMethod)
    {
        $this->requestMethod = $requestMethod;
        return $this;
    }
 
    /**
     * Sets store id.
     *
     * @param int $storeId
     * @param string $contentType
     */
    public function setStoreId(int $storeId, string $contentType = 'application/json')
    {
        $this->storeId = $storeId;
        $this->contentType = $contentType;
        return $this;
    }
 
    /**
     * Check if method is acceptable.
     *
     * @param string $method
     * @return bool
     */
    private function isAcceptableMethod($method)
    {
        return in_array($method, [
            Request::METHOD_GET,
            Request::METHOD_DELETE,
            Request::METHOD_POST,
            Request::METHOD_PUT
        ], true);
    }
 
    /**
     * Get API host based on environment.
     *
     * @param int|null $storeId
     * @return string
     */
    private function getApiHost($storeId = null)
    {               
        return ($this->config->getEnvironment($storeId) == 'sandbox')
            ? self::TEST_API_HOST_REST
            : self::API_HOST_REST;
    }
 
    /**
     * Retrieve module config object.
     *
     * @return Config
     */
    protected function getConfigObject()
    {
        if (!empty($this->moduleConfigPath)) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            /** @var Config $config */
            $config = $objectManager->create('\\' . $this->moduleConfigPath);
        } else {
            $config = $this->config;
        }
        return $config;
    }
 
    /**
     * Recursively masks sensitive values in the array.
     *
     * @param array $data
     * @param array $keysToMask
     * @return array
     */
    private function maskSensitiveData(array $data, array $keysToMask): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value, $keysToMask);
            } elseif (in_array($key, $keysToMask, true)) {
                $data[$key] = str_repeat('*', strlen((string) $value));
            }
        }
        return $data;
    }
}