<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Plugin;

class PaymentTokenRepositoryPlugin
{
    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $serializer;

    /**
     * @var \Magento\Framework\Session\SessionManagerInterface
     */
    private $checkoutSession;

    /**
     * @var \Magento\Payment\Gateway\Config\Config
     */
    private $config;

    /**
     * @var string
     */
    private $configKey;

    /**
     * @var string
     */
    private $methodCode;

    /**
     * @param \Magento\Framework\Serialize\Serializer\Json $serializer
     * @param \Magento\Framework\Session\SessionManagerInterface $checkoutSession
     * @param \Magento\Payment\Gateway\Config\Config $config
     * @param null|string $configKey
     * @param null|string $methodCode
     */
    public function __construct(
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        \Magento\Framework\Session\SessionManagerInterface $checkoutSession,
        \Magento\Payment\Gateway\Config\Config $config,
        $configKey = null,
        $methodCode = null
    ) {
        $this->serializer = $serializer;
        $this->checkoutSession = $checkoutSession;
        $this->config = $config;
        $this->configKey = $configKey;
        $this->methodCode = $methodCode;
    }

    /**
     * Plugin to filter saved payment tokens by CyberSource merchant ID
     *
     * @param \Magento\Vault\Model\PaymentTokenRepository $subject
     * @param \Magento\Vault\Api\Data\PaymentTokenSearchResultsInterface $result
     *
     * @return \Magento\Vault\Api\Data\PaymentTokenSearchResultsInterface
     */
    public function afterGetList(\Magento\Vault\Model\PaymentTokenRepository $subject, $result)
    {
        if (!$this->methodCode) {
            return $result;
        }

        $validTokens = [];
        foreach ($result->getItems() as $token) {
            if ($this->isValidToken($token)) {
                $validTokens[] = $token;
            }
        }

        return $result->setItems($validTokens);
    }

    /**
     * Filter saved payment tokens by CyberSource merchant ID
     *
     * @param \Magento\Vault\Api\Data\PaymentTokenInterface $token
     *
     * @return bool
     */
    private function isValidToken($token)
    {
        if ($token->getPaymentMethodCode() != $this->methodCode) {
            return true;
        }

        try {
            $details = $this->serializer->unserialize($token->getTokenDetails());
            $tokenMerchantId = $details['merchantId'] ?? null;
            if ($tokenMerchantId !== null) {
                $currentStoreId = $this->checkoutSession->getStore()
                    ? $this->checkoutSession->getStore()->getId()
                    : $this->checkoutSession->getQuote()->getStoreId();

                $environment = $this->config->getValue('environment');
                $merchantIdConfigKey = $environment === 'sandbox' ? 'merchant_id_sandbox' : 'merchant_id_production';
                $configKey = $merchantIdConfigKey;

                return $this->config->getValue($configKey, $currentStoreId) == $tokenMerchantId;
            }
        } catch (\Exception $e) {
            // ignore the exception, but log it
        }

        return true;
    }
}
