<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Command;

class CreateRequestCommand implements \Magento\Payment\Gateway\CommandInterface
{
    /**
     * @var \Magento\Payment\Gateway\Command\Result\ArrayResultFactory
     */
    private $arrayResultFactory;

    /**
     * @var \Magento\Payment\Gateway\Request\BuilderInterface
     */
    private $requestBuilder;

    /**
     * @var \CyberSource\Payment\Model\LoggerInterface
     */
    private $logger;

    /**
     * @param \Magento\Payment\Gateway\Command\Result\ArrayResultFactory $arrayResultFactory
     * @param \Magento\Payment\Gateway\Request\BuilderInterface $requestBuilder
     * @param \CyberSource\Payment\Model\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Payment\Gateway\Command\Result\ArrayResultFactory $arrayResultFactory,
        \Magento\Payment\Gateway\Request\BuilderInterface $requestBuilder,
        \CyberSource\Payment\Model\LoggerInterface $logger
    ) {
        $this->arrayResultFactory = $arrayResultFactory;
        $this->requestBuilder = $requestBuilder;
        $this->logger = $logger;
    }

    /**
     * Builds create Token Request
     *
     * @param array $commandSubject
     * @return \Magento\Payment\Gateway\Command\ResultInterface
     * @throws \Magento\Payment\Gateway\Command\CommandException
     */
    public function execute(array $commandSubject)
    {

        $request = $this->requestBuilder->build($commandSubject);

        $this->logger->debug(
            [
                'client' => static::class,
                'request' => $request
            ]
        );

        return $this->arrayResultFactory->create(['array' => $request]);
    }
}
