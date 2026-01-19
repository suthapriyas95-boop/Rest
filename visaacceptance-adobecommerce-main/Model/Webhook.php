<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model;

/**
 * Class Token
 * @codeCoverageIgnore
 */
class Webhook extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Webhook model
     */
    protected function _construct()
    {
        $this->_init('CyberSource\Payment\Model\ResourceModel\Webhook');
    }

    /**
     * Get webhook ID by product ID and organization ID
     *
     * @param string $productId
     * @param string $organizationId
     * @return string|null
     */
 
    public function getWebhookIdByProductAndOrganization($productId, $organizationId)
    {
        /** @var \Magento\Framework\Model\ResourceModel\Db\AbstractDb $resource */
        $resource = $this->_getResource();
        $connection = $resource->getConnection();
        $select = $connection->select()
            ->from($resource->getMainTable(), 'webhook_id')
            ->where('product_id = ?', $productId)
            ->where('organization_id = ?', $organizationId);

        return $connection->fetchOne($select);
    }
}
