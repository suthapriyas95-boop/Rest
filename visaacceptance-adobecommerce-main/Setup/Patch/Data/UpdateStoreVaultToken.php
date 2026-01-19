<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace CyberSource\Payment\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use CyberSource\Payment\Model\Ui\ConfigProvider;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/**
* Patch is mechanism, that allows to do atomic upgrade data changes
*/
class UpdateStoreVaultToken implements DataPatchInterface
{

    /**
     * @var ModuleDataSetupInterface $moduleDataSetup
     */
    private $moduleDataSetup;
	
	 /**
     * @var \Magento\Vault\Model\PaymentTokenRepository
     */
    private $paymentTokenRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var \CyberSource\Payment\Model\Config
     */
    private $config;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $serializer;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
	 * @param \Magento\Vault\Model\PaymentTokenRepository $paymentTokenRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \CyberSource\Payment\Model\Config $config
     * @param \Magento\Framework\Serialize\Serializer\Json $serializer
     */
    public function __construct(
		ModuleDataSetupInterface $moduleDataSetup,
		\Magento\Vault\Model\PaymentTokenRepository $paymentTokenRepository,
		\Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
		\CyberSource\Payment\Model\Config $config,
		\Magento\Framework\Serialize\Serializer\Json $serializer
	)
    {
        $this->moduleDataSetup = $moduleDataSetup;
		$this->paymentTokenRepository = $paymentTokenRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->config = $config;
        $this->serializer = $serializer;
    }

    /**
     * Do Upgrade
     *
     * @return void
     */
    public function apply()
    {
		$searchCriteria = $this->searchCriteriaBuilder->addFilter(
            PaymentTokenInterface::PAYMENT_METHOD_CODE,
            ConfigProvider::CODE
        )->create();

        $tokens =  $this->paymentTokenRepository->getList($searchCriteria)->getItems();

        foreach ($tokens as $token) {
            try {
                $details = $this->serializer->unserialize($token->getTokenDetails());
                if (!empty($details['merchantId']) || empty($details['incrementId'])) {
                    continue;
                }
                $select = $this->moduleDataSetup->getConnection()
                    ->select()
                    ->from($this->moduleDataSetup->getTable('sales_order'), ['store_id'])
                    ->where('increment_id = ?', $details['incrementId']);

                if (! $storeId = $this->moduleDataSetup->getConnection()->fetchOne($select)) {
                    continue;
                }
                $environment = $this->config->getValue('environment');
                $configKey = $environment === 'sandbox' ? 'merchant_id_sandbox' : 'merchant_id_production';

                $details['merchantId'] = $this->config->getValue(
                   $configKey,
                    $storeId
                );

                $token->setTokenDetails($this->serializer->serialize($details));
                $this->paymentTokenRepository->save($token);
            } catch (\Exception $e) {

            }
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
