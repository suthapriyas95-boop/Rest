<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

use Magento\Framework\UrlInterface;
use Magento\Checkout\Model\Session;
use CyberSource\Payment\Model\Config;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use CyberSource\Payment\Model\Adminhtml\Source\Country;
use Magento\Framework\Locale\Resolver;
use CyberSource\Payment\Helper\AbstractDataBuilder;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Builds capture context
 */
class GenerateCaptureContextRequest implements BuilderInterface
{   
    private const XML_PATH_DEFAULT_COUNTRY = 'general/country/default';
    /**
     * @var Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var  CyberSource\Payment\Model\Config
     */
    private $gatewayConfig;

    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    private $locale;

    /**
     * @var CyberSource\Payment\Model\Adminhtml\Source\Country
     */
    private $country;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * GenerateCaptureContextRequest constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param Session $checkoutSession
     * @param Config $gatewayConfig
     * @param Resolver $locale
     * @param Country $country
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Session $checkoutSession,
        Config $gatewayConfig,
        Resolver $locale,
        Country $country,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->gatewayConfig = $gatewayConfig;
        $this->locale = $locale;
        $this->country = $country;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Builds capture context
     *
     * @param array $buildSubject
     *
     * @return array
     */
    public function build(array $buildSubject)
    {
        $quote = $this->checkoutSession->getQuote();
        $storeFullUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
        $urlComponents = parse_url($storeFullUrl);
        $request = new \stdClass();
        $request->targetOrigins[] = $urlComponents['scheme'] . '://' . $urlComponents['host'];
        $request->clientVersion = "0.33";
        $request->allowedCardNetworks = $this->gatewayConfig->getAllowedNetworks();
        $request->allowedPaymentTypes = $this->gatewayConfig->getAllowedPayments();
        $request->transientTokenResponseOptions = ['includeCardPrefix' => false];        
        $storeId = (int)$quote->getStoreId();
        if ($storeId === null) {
            $storeId = (int)$this->storeManager->getStore()->getId();
        }        
        $request->country = $this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_COUNTRY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $request->locale = $this->locale->getLocale();
        $captureMandate = new \stdClass();
        $captureMandate->billingType = "NONE";
        $captureMandate->requestEmail = false;
        $captureMandate->requestPhone = false;
        $captureMandate->requestShipping = false;
        $captureMandate->shipToCountries = $this->getAllowedCountries();
        $captureMandate->showAcceptedNetworkIcons = true;
        $request->captureMandate = $captureMandate;

        $orderInformation = new \stdClass();

        $amountDetails = new \stdClass();
        $amountDetails->totalAmount = $this->formatAmount($quote->getGrandTotal());
        $amountDetails->currency = $quote->getQuoteCurrencyCode();

        $orderInformation->amountDetails = $amountDetails;

        $orderInformation->billTo = $this->buildAddress($quote->getBillingAddress());
        $orderInformation->billTo['email'] = $quote->getShippingAddress()->getEmail();
        $orderInformation->billTo['phoneNumber'] = $quote->getBillingAddress()->getTelephone();

        $request->orderInformation = $orderInformation;

        // Convert request to array and mask sensitive data before logging
        $requestArray = json_decode(json_encode($request), true);
        $maskedRequestArray = $this->maskSensitiveData($requestArray);

        return $requestArray;
    }

    /**
     * Builds address
     *
     * @param \Magento\Quote\Model\Quote\Address $address
     *
     * @return array
     */
    private function buildAddress(\Magento\Quote\Model\Quote\Address $address)
    {
        return [
            'firstName' => $address->getFirstname(),
            'lastName' => $address->getLastname(),
        ];
    }

    /**
     * Gets allowed countries
     *
     * @return array
     */
    public function getAllowedCountries()
    {
        if ($this->gatewayConfig->getAllowspecific() === '1') {
            return explode(',', $this->gatewayConfig->getSpecificcountry() ?? '');
        } else {
            return array_column($this->country->toOptionArray(), 'value');
        }
    }

    /**
     * Formats amount
     *
     * @param float $amount
     *
     * @return string
     */
    private function formatAmount($amount)
    {
        if (!is_float($amount)) {
            $amount = (float)$amount;
        }

        return sprintf('%.2F', $amount);
    }

    /**
     * Masks sensitive data in the request.
     *
     * @param array $data
     * @return array
     */
    private function maskSensitiveData(array $data)
    {
        if (isset($data['orderInformation']['billTo'])) {
            $data['orderInformation']['billTo']['firstName'] = '****';
            $data['orderInformation']['billTo']['lastName'] = '****';
            $data['orderInformation']['billTo']['email'] = '****@****.com';
            $data['orderInformation']['billTo']['phoneNumber'] = '**********';
        }
        return $data;
    }
}
