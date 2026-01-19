<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

class OrganizationIdBuilder implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    /**
     * @var \CyberSource\Payment\Model\Config
     */
    private $config;

    /**
     * @param \CyberSource\Payment\Model\Config $config
     */

    public function __construct(
        \CyberSource\Payment\Model\Config $config
    ) {
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {

        $storeId = $buildSubject['store_id'] ?? null;

        return [
            'organizationId' => $this->config->getMerchantId($storeId),
        ];
    }
}
