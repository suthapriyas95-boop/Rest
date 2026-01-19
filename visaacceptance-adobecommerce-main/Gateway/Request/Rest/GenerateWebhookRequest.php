<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

class GenerateWebhookRequest implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    /**
     * @var \CyberSource\Payment\Model\Config
     */
    private $config;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $url;

     /**
      * @var \Magento\Store\Model\StoreManagerInterface
      */
    private $storeManager;

    /**
     * Constructor
     *
     * @param \CyberSource\Payment\Model\Config $config
     * @param \Magento\Framework\UrlInterface $url
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \CyberSource\Payment\Model\Config $config,
        \Magento\Framework\UrlInterface $url,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->url = $url;
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
            'name' => 'My Custom Webhook',
            'description' => 'Sample Webhook from Developer Center',
            'organizationId' => $merchantID,
            'productId' => 'decisionManager',
            "eventTypes" => [
                "risk.casemanagement.decision.accept",
                "risk.casemanagement.decision.reject",
                "risk.casemanagement.addnote",
            ],
            'webhookUrl' => $this->url->getUrl('cybersourcePayment/frontend/WebhookDecisionManagerController'),
            'healthCheckUrl' => $this->url->getUrl('cybersourcePayment/frontend/WebhookDecisionManagerController'),
            'notificationScope' => 'SELF',
            'retryPolicy' => [
                'algorithm' => 'ARITHMETIC',
                'firstRetry' => 1,
                'interval' => 1,
                'numberOfRetries' => 3,
                'deactivateFlag' => 'false',
                'repeatSequenceCount' => 0,
                'repeatSequenceWaitTime' => 0
            ],
            'securityPolicy' => [
                'securityType' => 'KEY',
                'proxyType' => 'external'
            ]
        ];
    }
}
