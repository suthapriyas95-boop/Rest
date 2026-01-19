<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model;

/**
 * Class Config
 * Access configuration of all child modules
 * @codeCoverageIgnore
 */
abstract class AbstractGatewayConfig extends \Magento\Payment\Gateway\Config\Config
{
    public const KEY_ACTIVE = 'active';
    public const KEY_TITLE = 'title';
    public const KEY_DEBUG_MODE = 'debug';
    public const KEY_PAYMENT_ACTION = 'payment_action';
    public const ENVIRONMENT = 'environment';
    public const KEY_MERCHANT_ID = 'merchant_id';
    public const KEY_REST_SHARED_KEY_ID = 'api_key_';
    public const KEY_REST_SHARED_KEY_VALUE = 'api_shared_secret_';
    public const KEY_REST_MERCHANT_ID = 'merchant_id_';
    public const KEY_ALLOWSPECIFIC = 'allowspecific';
    public const KEY_SPECIFICCOUNTRY = 'specificcountry';
    public const KEY_CCTYPES = 'cctypes';
    public const KEY_ENABLE_CVV = 'enable_cvv';
    public const PATH = 'payment/cybersource_section/cybersource/';
    public const KEY_ALLOWED_NETWORKS = 'credit_card_types';
    public const KEY_ALLOWED_PAYMENTS = 'allowed_payments';
    public const KEY_UC_LAYOUT = 'uc_layout';
    public const KEY_TOKEN_CREATE = 'TOKEN_CREATE';
    public const KEY_PAYMENT_INSTRUMENT  = 'paymentInstrument';
    public const KEY_INSTRUMENT_IDENTIFIER = 'instrumentIdentifier';
    public const KEY_CUSTOMER = 'customer';
    public const KEY_VAULT_ENABLE = 'active';
    public const KEY_IS_ACTIVE = 'active_3ds';
    public const KEY_ENFORCE_SCA_ON_SAVE = 'enforce_sca_on_save';
    public const KEY_DECISION_MANAGER_ENABLE = 'decision_manager';
    public const P12_CERTIFICATE="payment/unifiedcheckout/p12_certificate";
    public const P12_ACCESSKEY="p12_accesskey";
    public const MLE_ENABLE="mle_enable";


    /**
     * Returns Whether the payment method is active
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->getValue(self::KEY_ACTIVE);
    }

    /**
     * Returns Title of the payment method
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getValue(self::KEY_TITLE);
    }

    /**
     * Returns Whether debug mode is enabled
     *
     * @return bool
     */
    public function getDebugMode()
    {
        return $this->getValue(self::KEY_DEBUG_MODE);
    }

    /**
     * Returns Whether specific country is allowed
     *
     * @return bool
     */
    public function getAllowspecific()
    {
        return $this->getValue(self::KEY_ALLOWSPECIFIC);
    }

    /**
     * Returns Specific country
     *
     * @return string
     */
    public function getSpecificcountry()
    {
        return $this->getValue(self::KEY_SPECIFICCOUNTRY);
    }

    /**
     * Returns Available credit card types
     *
     * @return string
     */
    public function getCcTypes()
    {
        return $this->getValue(self::KEY_CCTYPES);
    }

    /**
     * Returns Merchant ID
     *
     * @param string $storeId
     * @return string
     */
    public function getMerchantId($storeId = null)
    {
        return $this->getValue(self::KEY_MERCHANT_ID, $storeId);
    }

    /**
     * Returns Whether CVV is enabled
     *
     * @return bool
     */
    public function isCVVEnabled()
    {
        return (bool) $this->getValue(self::KEY_ENABLE_CVV);
    }

    /**
     * Returns Whether Decision Manager is enabled
     *
     * @return bool
     */
    // public function getDecisionManagerEnabled()
    // {
    //     return (bool) $this->getValue(self::KEY_DECISION_MANAGER_ENABLE);
    // }

    /**
     * Returns Current store ID
     *
     * @return string
     */
    public function getCurrentStoreId()
    {
        $objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $storeId = $storeManager->getStore()->getStoreId();
        return $storeId;
    }

    /**
     * Returns Supported networks
     *
     * @param string $value
     * @return array
     */
    public function getSupportedNetworks($value)
    {
        $ccTypesMap = [
            'VI' => 'VISA',
            'MC' => 'MASTERCARD',
            'AE' => 'AMEX',
            'DI' => 'DISCOVER',
            'JCB' => 'JCB',
            'DN' => 'DINERSCLUB',
            'JW'  => 'JAYWAN'
        ];

        $getAllowedNetworksinArray = explode(",", $value);

        $result = [];

        foreach ($getAllowedNetworksinArray as $type) {
            if (isset($ccTypesMap[$type])) {
                $result[] = $ccTypesMap[$type];
            }
        }

        return $result;
    }

    public function isMle(){
        return $this->getValue(self::MLE_ENABLE);
    }
    
    public function getAccessKey(){
        return $this->getValue(self::P12_ACCESSKEY);
    }
    
}
