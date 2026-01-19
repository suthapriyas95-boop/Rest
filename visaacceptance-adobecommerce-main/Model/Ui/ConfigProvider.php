<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use CyberSource\Payment\Model\Config;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;



class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'unifiedcheckout';

    public const CC_VAULT_CODE = 'unifiedcheckout_vault';

    public const PAYMENT_TOKEN = 'paymentToken';

    public const SOLUTION_ID = '001';
    public const P12_CERTIFICATE="payment/unifiedcheckout/p12_certificate";


    /**
     * @var ResolverInterface
     */
    private $localeResolver;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $url;
    private $scopeConfig;


    /**
     * ConfigProvider constructor.
     * @param Config $config
     * @param ResolverInterface $localeResolver
     * @param \Magento\Framework\UrlInterface $url
     */
    public function __construct(
        Config $config,
        ResolverInterface $localeResolver,
        \Magento\Framework\UrlInterface $url,
        ScopeConfigInterface $scopeConfig

    ) {
        $this->config = $config;
        $this->localeResolver = $localeResolver;
        $this->url = $url;
        $this->scopeConfig=$scopeConfig;

    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'isActive' => $this->config->isActive(),
                    'title' => $this->config->getTitle(),
                    'vaultCode' => self::CC_VAULT_CODE,
                    'vault_enable' => $this->config->isVaultEnabled(),
                ],
                    self::CC_VAULT_CODE => [
                        "is_cvv_enabled" => $this->config->isCVVEnabled()
                    ]
            ]
        ];
    }
    public function getP12Certificate(){
        return $this->getScopeValue(self::P12_CERTIFICATE);
    }
    public function getScopeValue($path)
    {
        return $this->scopeConfig->getValue($path,ScopeInterface::SCOPE_STORE);
    }
}
