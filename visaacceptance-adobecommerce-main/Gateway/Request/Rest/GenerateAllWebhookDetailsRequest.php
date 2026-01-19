<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\StoreManagerInterface;
use CyberSource\Payment\Model\Config;
use CyberSource\Payment\Observer\SaveConfigObserver;

class GenerateAllWebhookDetailsRequest implements BuilderInterface
{
    /**
     * @var storeManagerInterface
     */
    private $storeManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var SaveConfigObserver
     */
    private $saveConfigObserver;

    /**
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param SaveConfigObserver $saveConfigObserver
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Config $config,
        SaveConfigObserver $saveConfigObserver
    ) {
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->saveConfigObserver = $saveConfigObserver;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $merchantID = $this->config->getMerchantId($storeId);
        $urlParams = http_build_query([
            'organizationId' => $merchantID,
            'productId' => 'decisionManager',
            'eventType' => 'risk.casemanagement.decision.accept'

        ]);

        // Return the URL parameters as part of the build subject
        return ['url_params' => [$urlParams,'?']];
    }
}
