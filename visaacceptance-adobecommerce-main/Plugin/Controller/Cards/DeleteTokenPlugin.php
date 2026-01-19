<?php
 
/**
 
* Copyright © 2018 CyberSource. All rights reserved.
 
* See accompanying LICENSE.txt for applicable terms of use and license.
 
*/
 
declare(strict_types=1);
 
namespace CyberSource\Payment\Plugin\Controller\Cards;
 
use CyberSource\Payment\Model\Config;
use Magento\Vault\Model\PaymentTokenManagement;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Url\DecoderInterface;
use CyberSource\Payment\Helper\Data;
 
/**
 
* Class DeleteActionPlugin
 
* Plugin for delete token from CyberSource when deleting it from Magento
*/
 
class DeleteTokenPlugin
 
{
 
    private const HTTP_METHOD_DELETE = 'delete';
    private const DATE_D_D_M_Y_G_I_S_ = 'D, d M Y G:i:s ';
    private const API_HOST_REST_DELETE = 'api.visaacceptance.com';
    private const TEST_API_HOST_REST_DELETE = 'apitest.visaacceptance.com';
 
    /**
     * @var PaymentTokenManagement
     */
    private $paymentTokenManagement;
 
    /**
     * @var PaymentTokenRepositoryInterface
     */
    private $paymentTokenRepository;
 
    /** 
     * @var Session 
    */
    private $customerSession;
 
    /** 
     * @var \Magento\Framework\App\Response\RedirectInterface
    */
    private $redirect;
 
    /**
     * @var \Magento\Framework\Message\ManagerInterface 
     */
    private $messageManager;
 
    /**
     * @var \Magento\Framework\App\ResponseInterface
     */
    private $config;

    /**
     * @var \Magento\Framework\Url\DecoderInterface
     */
    protected $urlDecoder;
 
 
    /**
     * DeleteTokenPlugin constructor.
     * @param PaymentTokenManagement $paymentTokenManagement
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param Session $customerSession
     * @param \Magento\Framework\App\Response\RedirectInterface $redirect
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param Config $config 
     * @param \Magento\Framework\Url\DecoderInterface $urlDecoder
     */
 
    public function __construct(
        PaymentTokenManagement $paymentTokenManagement,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        Session $customerSession,
        \Magento\Framework\App\Response\RedirectInterface $redirect,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        Config $config,
        DecoderInterface $urlDecoder
    ) {
 
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->customerSession = $customerSession;
        $this->redirect = $redirect;
        $this->messageManager = $messageManager; 
        $this->config = $config;
        $this->urlDecoder = $urlDecoder;
 
    }
 
    /**
     * Plugin for delete token from CyberSource when deleting it from Magento
     *
     * @param \Magento\Vault\Controller\Cards\DeleteAction $subject
     * @param \Closure $proceed
     *
     * @return mixed
     */
 
    public function aroundExecute(\Magento\Vault\Controller\Cards\DeleteAction $subject, \Closure $proceed)
    {
 
        $request = $subject->getRequest();
        $paymentToken = $this->getPaymentToken($request);
 
        if ($paymentToken !== null && !empty($paymentToken->getData())) {
            if ($paymentToken->getPaymentMethodCode() != \CyberSource\Payment\Model\Ui\ConfigProvider::CODE) {
                return $proceed();
            }
            $result =  $this->deleteSavedCard($paymentToken);
            if ($result && $result['http_code'] !== 204) { 
                $this->messageManager->addErrorMessage(
                    __('Unable to delete the card. Please try again. Error: %1', $result['http_code'])
                );
                $this->redirect->redirect($subject->getResponse(), 'vault/cards/listaction');
            } else {
                // Delete token from Magento vault_payment_token table
                try {
                    $this->paymentTokenRepository->delete($paymentToken);
                } catch (\Exception $e) {
                }
                return $proceed();
            }
        }
    }
    /**
     * Get payment token from request
     *
     * @param Http $request
     * @return PaymentTokenInterface|null
     */
 
    private function getPaymentToken(Http $request)
    {
        $publicHash = $request->getPostValue(PaymentTokenInterface::PUBLIC_HASH);
        if ($publicHash === null) {
            return null;
        }
        return $this->paymentTokenManagement->getByPublicHash(
            $publicHash,
            $this->customerSession->getCustomerId()
        );
    } 
    /**
     * Delete saved card from CyberSource
     *
     * @param \Magento\Vault\Model\PaymentToken $tokenData 
     * @return array
     */
 
    private function deleteSavedCard($tokenData) 
    {
        $tokenDetails = json_decode($tokenData->getTokenDetails(), true);
        $payload = '';
        $customerID = $tokenData->getGatewayToken();
        $paymentInstrument = $tokenDetails['paymentInstrumentIdentifier'];
        $resource = '/tms/v2/customers/' . $customerID . '/payment-instruments/' . $paymentInstrument;
        return $this->serviceProcessor($payload, $resource, true, self::HTTP_METHOD_DELETE);
    }
    /**
     * Service processor 
     *
     * @param string $payload
     * @param string $resource
     * @param bool $serviceHeader
     * @param string $service
     *
     * @return array
     */
 
    private function serviceProcessor($payload, $resource, $serviceHeader, $service)
    { 
            $payload = '';
            $method = 'delete';
            $date = gmdate('D, d M Y G:i:s ') . 'GMT';
            $resource_encode = mb_convert_encoding($resource, 'UTF-8');
            $hostUrl = 'https://' . $this->getApiHost();
        ;
            $url = $hostUrl . $resource;
            $requestHost = self::removeHttp($hostUrl);
            $headerParams = []; 
            $headers = [];
            $headerParams['Accept'] = 'application/json;';
            $headerParams['Content-Type'] = 'application/json;';
            foreach ($headerParams as $key => $val) {
                $headers[] = "$key: $val";
            }
            $authHeaders = $this->getHttpSignatureGet($resource_encode, $method, $date, $requestHost);
            $headerParams = array_merge($headers, $authHeaders);
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headerParams);
            curl_setopt($curl, CURLOPT_URL, $url); 
            curl_setopt($curl, CURLOPT_HEADER, 1); 
            curl_setopt($curl, CURLOPT_VERBOSE, 0);
            curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0');
			
			// curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            // curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
           
            $response = curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
            $response = curl_exec($curl);
            if ($response === false) {
                curl_close($curl);
                return ['http_code' => 0, 'error' => curl_error($curl), 'header' => '', 'body' => ''];
            }
            $response = self::getResponseArray($curl, $response);
            curl_close($curl);
            return $response;
    }
    /**
     * Get HTTP signature for GET method 
     *
     * @param string $resource 
     * @param string $httpMethod 
     * @param string $currentDate
     * @param string $requestHost
     *
     * @return array
     */
 
    public function getHttpSignatureGet($resource, $httpMethod, $currentDate, $requestHost)
    {
        $merchantID = $this->config->getMerchantId();
        $keyID = $this->config->getRestKeyId();
        $secretKey = $this->config->getRestKeyValue();
        $signatureString = 'host: ' . $requestHost . "\ndate: " . 
        $currentDate . "\n(request-target): " . $httpMethod . ' ' .
        $resource . "\nv-c-merchant-id: " . $merchantID ;
        $headerString = 'host date (request-target) v-c-merchant-id'; 
        $signatureByteString = mb_convert_encoding($signatureString, 'UTF-8'); 
        $decodeKey =  $this->urlDecoder->decode($secretKey); 
        $signature = base64_encode(hash_hmac(
            'sha256',
            $signatureByteString, 
            $decodeKey, 
            true 
        ));
        $signatureHeader = [
            'keyid="' . $keyID . '"',
            'algorithm="HmacSHA256"',
            'headers="' . $headerString . '"',
            'signature="' . $signature . '"',
 
        ]; 
        $signatureToken = 'Signature:' . implode(', ', $signatureHeader);
        $host = 'Host:' . $requestHost;
        $vcMerchantId = 'v-c-merchant-id:' . $merchantID;
        $headers = [ 
            $vcMerchantId,
            $signatureToken,
            $host,
            'Date:' . $currentDate,
        ];
        return $headers; 
    }
 
    /**
     * Get response array from curl response 
     * 
     * @param resource $curl 
     * @param string $response 
     * 
     * @return array
     */
 
    public function getResponseArray($curl, $response)
    {
        $responseArray = [];
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
 
        // Fix TypeError: Check if response is valid before using substr
 
        if ($response === false || !is_string($response)) {
            $responseArray['header'] = '';
            $responseArray['body'] = '';
            $responseArray['error'] = 'Invalid response';
        } else { 
            $responseArray['header'] = substr($response, 0, $header_size);
            $responseArray['body'] = substr($response, $header_size);
        }
        $responseArray['http_code'] = curl_getinfo($curl, CURLINFO_HTTP_CODE); 
        return $responseArray; 
    }
    /**
     * Remove http from url
     *
     * @param string $url
     * @return string
     */
 
    public function removeHttp($url)
    {
        $disallowed = ['http://','https://'];
        foreach ($disallowed as $d) {
            if (0 === strpos($url, $d)) {
                return str_replace($d, '', $url); 
            } 
        }
        return $url; 
    }
 
    /**
     * Get CyberSource API host
     *
     * @param int|null $storeId
     *
     * @return string
     */
 
    private function getApiHost($storeId = null)
    {
        return ($this->config->getEnvironment($storeId) == 'sandbox') ? self::TEST_API_HOST_REST_DELETE : self::API_HOST_REST_DELETE;
    }
 
}