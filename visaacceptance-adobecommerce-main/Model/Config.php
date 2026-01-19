<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model;

use CyberSource\Payment\Model\Ui\ConfigProvider;

class Config extends AbstractGatewayConfig
{
    public const CODE = 'unifiedcheckout';

    /**
     * Returns the environment for the given storeId.
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getEnvironment($storeId = null)
    {
        return $this->getValue(self::ENVIRONMENT, $storeId);
    }

    /**
     * Returns the REST key ID for the given storeId.
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getRestKeyId($storeId = null)
    {
        $field = self::KEY_REST_SHARED_KEY_ID . $this->getEnvironment($storeId);
        return $this->getValue($field, $storeId);
    }

    /**
     * Returns the REST key value for the given storeId.
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getRestKeyValue($storeId = null)
    {
        $field = self::KEY_REST_SHARED_KEY_VALUE . $this->getEnvironment($storeId);
        return $this->getValue($field, $storeId);
    }

    /**
     * Returns the merchant ID for the given storeId.
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getMerchantId($storeId = null)
    {
        $field = self::KEY_REST_MERCHANT_ID . $this->getEnvironment($storeId);
        return $this->getValue($field, $storeId);
    }

    /**
     * Returns the allowed networks for the given storeId.
     *
     * @param int|null $storeId
     *
     * @return array
     */
    public function getAllowedNetworks()
    {
        $value = $this->getValue(self::KEY_ALLOWED_NETWORKS);
        return $this->getSupportedNetworks($value);
    }

    /**
     * Returns the allowed payments for the given storeId.
     *
     * @param int|null $storeId
     *
     * @return array
     */
    public function getAllowedPayments()
    {
        return explode(",", $this->getValue(self::KEY_ALLOWED_PAYMENTS));
    }

    /**
     * Returns the user check out layout for the given storeId.
     *
     * @param int|null $storeId
     *
     * @return array
     */
    public function getUcLayout()
    {
        return $this->getValue(self::KEY_UC_LAYOUT);
    }

    /**
     * Returns the vault title for the given storeId.
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getVaultTitle()
    {
        $this->setMethodCode(ConfigProvider::CC_VAULT_CODE);
        $title = $this->getValue(self::KEY_TITLE);
        $this->setMethodCode(ConfigProvider::CODE);
        return $title;
    }

    /**
     * Returns whether vault is enabled for the given storeId.
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isVaultEnabled()
    {
        return  $this->isVaultEnabledConfiguredOption();
    }

    /**
     * Returns whether vault is enabled for the given storeId.
     *
     * @param int|null $storeId The storeId to get the vault enabled flag for.
     *
     * @return bool
     */
    public function isVaultEnabledConfiguredOption($storeId = null)
    {
        $this->setMethodCode(ConfigProvider::CC_VAULT_CODE);
        $isVaultEnable = (bool) $this->getValue(self::KEY_VAULT_ENABLE, $storeId);
        $this->setMethodCode(ConfigProvider::CODE);
        return $isVaultEnable;
    }

    /**
     * Returns whether payer auth is enabled.
     *
     * @return bool
     */
    public function isPayerAuthEnabled()
    {
        return $this->getValue(self::KEY_IS_ACTIVE);
    }

    /**
     * Returns whether SCA is enforced on card save.
     *
     * @return bool
     */
    public function isScaEnforcedOnCardSave()
    {
        return $this->getValue(self::KEY_ENFORCE_SCA_ON_SAVE);
    }
}
