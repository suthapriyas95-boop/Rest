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

class GenerateGetEventRequest implements BuilderInterface
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
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Config $config
    ) {
        $this->storeManager = $storeManager;
        $this->config = $config;
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
        return['url_params' => [$merchantID ]];
    }
}
