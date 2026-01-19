<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model\ResourceModel\Webhook;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Webhook collection
 */
class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init('CyberSource\Payment\Model\Webhook', 'CyberSource\Payment\Model\ResourceModel\Webhook');
    }
}
