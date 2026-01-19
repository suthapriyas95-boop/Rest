<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Observer;

class SubmitObserver implements \Magento\Framework\Event\ObserverInterface
{
    public const MINIMAL_VERSION = '2.2.5';

    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * SubmitObserver constructor.
     *
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        $this->productMetadata = $productMetadata;
        $this->objectManager = $objectManager;
    }

    /**
     * @inheritdoc
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (version_compare($this->productMetadata->getVersion(), self::MINIMAL_VERSION, '>=')) {
            return;
        }

        /** @var \Magento\Quote\Observer\Webapi\SubmitObserver $submitObserver */
        $submitObserver = $this->objectManager->get(\Magento\Quote\Observer\Webapi\SubmitObserver::class);

        $submitObserver->execute($observer);
    }
}
