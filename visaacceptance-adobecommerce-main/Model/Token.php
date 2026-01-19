<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model;

class Token extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Token model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\CyberSource\Payment\Model\ResourceModel\Token::class);
    }
}
