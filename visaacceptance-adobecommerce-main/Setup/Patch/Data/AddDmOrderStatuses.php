<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Patch is mechanism, that allows to do atomic upgrade data changes
 */
class AddDmOrderStatuses implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface $moduleDataSetup
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * Do Upgrade
     *
     * @return void
     */
    public function apply()
    {
        $orderStatus = 'dm_refund_review';

        $select = $this->moduleDataSetup->getConnection()->select()
                    ->from($this->moduleDataSetup->getTable('sales_order_status'), ['status'])
                    ->where('status = ?', $orderStatus);

        $dmStatus = $this->moduleDataSetup->getConnection()->fetchOne($select);

        if (empty($dmStatus)) {
             /**
             * Prepare database for install
             */
            $this->moduleDataSetup->getConnection()->startSetup();

            $data = [];

            $statuses = ['dm_refund_review' => __('DM Refund Review')];

            foreach ($statuses as $code => $info) {
                $data[] = ['status' => $code, 'label' => $info];
            }

            $this->moduleDataSetup->getConnection()->insertArray(
                $this->moduleDataSetup->getTable('sales_order_status'),
                ['status', 'label'],
                $data
            );
            /**
             * Prepare database after install
             */
            $this->moduleDataSetup->getConnection()->endSetup();
        }
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }
}
