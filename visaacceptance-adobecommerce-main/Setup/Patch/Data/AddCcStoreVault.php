<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use CyberSource\Payment\Model\LoggerInterface;
use CyberSource\Payment\Model\Ui\ConfigProvider;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/**
 * Patch is mechanism, that allows to do atomic upgrade data changes
 */
class AddCcStoreVault implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface $moduleDataSetup
     */
    private $moduleDataSetup;

     /**
      * @var LoggerInterface
      */
    private $logger;

    /**
     * @var string
     */
    private $token;

    /**
     *
     * @var \CyberSource\Payment\Model\ResourceModel\Token\Collection
     */
    private $tokenCollection;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var PaymentTokenFactoryInterface
     */
    private $paymentTokenFactory;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param LoggerInterface          $logger
     * @param Token                    $token
     * @param Collection               $tokenCollection
     * @param EncryptorInterface       $encryptor
     * @param PaymentTokenFactoryInterface $paymentTokenFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        LoggerInterface $logger,
        \CyberSource\Payment\Model\Token $token,
        \CyberSource\Payment\Model\ResourceModel\Token\Collection $tokenCollection,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Vault\Api\Data\PaymentTokenFactoryInterface $paymentTokenFactory
    ) {
        $this->moduleDataSetup      = $moduleDataSetup;
        $this->logger               = $logger;
        $this->token                = $token;
        $this->tokenCollection      = $tokenCollection;
        $this->encryptor            = $encryptor;
        $this->paymentTokenFactory = $paymentTokenFactory;
    }

    /**
     * Do Upgrade
     *
     * @return void
     */
    public function apply()
    {
        /**
         * Prepare database for install
        */
        $this->moduleDataSetup->getConnection()->startSetup();

        $this->tokenCollection->load();
        if ($this->tokenCollection->getSize() > 0) {
            foreach ($this->tokenCollection as $item) {
                try {
                    $paymentToken = $this->paymentTokenFactory->create(
                        \Magento\Vault\Api\Data\PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD
                    );
                    $paymentToken->setGatewayToken($item->getData('payment_token'));
                    $paymentToken->setExpiresAt($this->getExpirationDate($item->getData('card_expiry_date')));
                    $paymentToken->setIsVisible(true);
                    $paymentToken->setIsActive(true);
                    $paymentToken->setCustomerId($item->getData('customer_id'));
                    $paymentToken->setPaymentMethodCode(ConfigProvider::CODE);

                    $paymentToken->setTokenDetails($this->convertDetailsToJSON([
                        // 'title' => $this->config->getVaultTitle(),
                        'incrementId' => $item->getData('reference_number'),
                        'type' => $this->getCardType($item->getData('card_type'), true),
                        'maskedCC' => $item->getData('cc_last4'),
                        'expirationDate' => str_replace("-", "/", $item->getData('card_expiry_date'))
                    ]));

                    $paymentToken->setPublicHash($this->generatePublicHash($paymentToken));
                    $paymentToken->save();
                    $this->logger->notice("Token Id: " . $item->getId());
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                }
            }
        }

        /**
         * Prepare database after install
         */
        $this->moduleDataSetup->getConnection()->endSetup();
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

     /**
      * Get expiration date
      *
      * @param string $cardExpiry
      * @return string
      */
    private function getExpirationDate($cardExpiry)
    {
        $cardExpiry = explode("-", $cardExpiry ?? '');
        $expDate = new \DateTime(
            $cardExpiry[1]
            . '-'
            . $cardExpiry[0]
            . '-'
            . '01'
            . ' '
            . '00:00:00',
            new \DateTimeZone('UTC')
        );
        $expDate->add(new \DateInterval('P1M'));
        return $expDate->format('Y-m-d 00:00:00');
    }

    /**
     * Convert payment token details to JSON
     *
     * @param array $details
     * @return string
     */
    private function convertDetailsToJSON($details)
    {
        $json = \Laminas\Json\Json::encode($details);
        return $json ? $json : '{}';
    }

    /**
     * Generate vault payment public hash
     *
     * @param PaymentTokenInterface $paymentToken
     * @return string
     */
    private function generatePublicHash(PaymentTokenInterface $paymentToken)
    {
        $hashKey = $paymentToken->getGatewayToken();
        if ($paymentToken->getCustomerId()) {
            $hashKey = $paymentToken->getCustomerId();
        }

        $hashKey .= $paymentToken->getPaymentMethodCode()
            . $paymentToken->getType()
            . $paymentToken->getTokenDetails();

        return $this->encryptor->getHash($hashKey);
    }
    /**
     * Get card type
     *
     * @param string $code
     * @param bool $isMagentoType
     * @return string
     */
    private function getCardType($code, $isMagentoType = false)
    {
        $types = [
            'VI' => '001',
            'MC' => '002',
            'AE' => '003',
            'DI' => '004',
            'DN' => '005',
            'JCB' => '007',
            'MI' => '042',
            'JW' => '081',
        ];

        if ($isMagentoType) {
            $types = array_flip($types);
        }

        return (!empty($types[$code])) ? $types[$code] : $code;
    }
}
