<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Command;

use Exception;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Command\Result\ArrayResultFactory;

class DeleteWebhookSubscriptionCommand implements CommandInterface
{
    /**
     * @var TransferFactoryInterface
     */
    private $transferFactory;

    /**
     * @var ArrayResultFactory
     */
    private $arrayResultFactory;

    /**
     * @var BuilderInterface
     */
    private $requestBuilder;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @param TransferFactoryInterface $transferFactory
     * @param ArrayResultFactory $arrayResultFactory
     * @param BuilderInterface $requestBuilder
     * @param ClientInterface $client
     */
    public function __construct(
        TransferFactoryInterface $transferFactory,
        ArrayResultFactory $arrayResultFactory,
        BuilderInterface $requestBuilder,
        ClientInterface $client
    ) {
        $this->transferFactory = $transferFactory;
        $this->arrayResultFactory = $arrayResultFactory;
        $this->requestBuilder = $requestBuilder;
        $this->client = $client;
    }

    /**
     * Executes command basing on business logic specified in execute method of the command class.
     *
     * @param array $commandSubject
     * @return ResultInterface
     * @throws ClientException
     * @throws ConverterException
     * @throws Exception
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(array $commandSubject)
    {
        $transferO = $this->transferFactory->create(
            $this->requestBuilder->build($commandSubject)
        );

        $response = $this->client->placeRequest($transferO);

        return $this->arrayResultFactory->create(['array' => $response]);
    }
}
