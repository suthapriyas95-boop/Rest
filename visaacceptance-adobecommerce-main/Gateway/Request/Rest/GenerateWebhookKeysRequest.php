<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

class GenerateWebhookKeysRequest implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    /**
     * @var \CyberSource\Payment\Model\Config
     */
    private $config;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param \CyberSource\Payment\Model\Config $config
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \CyberSource\Payment\Model\Config $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $merchantID = $this->config->getMerchantId($storeId);
        return [
            'clientRequestAction' => 'CREATE',
            'keyInformation' => [
                'provider' => 'nrtd',
                'tenant' => $merchantID,
                'keyType' => 'sharedSecret',
                'organizationId' => $merchantID
                ]

        ];
    }
}
