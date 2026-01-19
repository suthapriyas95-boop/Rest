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

class PayerAuthSetUpBuilderCommand implements CommandInterface
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
     * @var \Magento\Framework\Session\StorageInterface
     */
    private $sessionStorage;

    /**
     * @param TransferFactoryInterface $transferFactory
     * @param ArrayResultFactory $arrayResultFactory
     * @param BuilderInterface $requestBuilder
     * @param ClientInterface $client
     * @param \Magento\Framework\Session\StorageInterface $sessionStorage
     */
    public function __construct(
        TransferFactoryInterface $transferFactory,
        ArrayResultFactory $arrayResultFactory,
        BuilderInterface $requestBuilder,
        ClientInterface $client,
        \Magento\Framework\Session\StorageInterface $sessionStorage
    ) {
        $this->transferFactory = $transferFactory;
        $this->arrayResultFactory = $arrayResultFactory;
        $this->requestBuilder = $requestBuilder;
        $this->client = $client;
        $this->sessionStorage = $sessionStorage;
    }

    /**
     * @inheritdoc
     *
     * @param array $commandSubject
     * @return ResultInterface
     * @throws ClientException
     * @throws ConverterException
     * @throws Exception
     */
    public function execute(array $commandSubject)
    {

        $transferO = $this->transferFactory->create(
            $this->requestBuilder->build($commandSubject)
        );

        $response = $this->client->placeRequest($transferO);

        $accessToken = $deviceDataCollectionUrl = $referenceId = null;

        if (isset($response['consumerAuthenticationInformation']['accessToken'])) {
            $accessToken = $response['consumerAuthenticationInformation']['accessToken'];
        }

        if (isset($response['consumerAuthenticationInformation']['deviceDataCollectionUrl'])) {
            $deviceDataCollectionUrl = $response['consumerAuthenticationInformation']['deviceDataCollectionUrl'];
        }

        if (isset($response['consumerAuthenticationInformation']['referenceId'])) {
            $referenceId = $response['consumerAuthenticationInformation']['referenceId'];

            $this->sessionStorage->setData('referenceId', $referenceId);
        }

        return $this->createArrayResult([
            'accessToken' => $accessToken,
            'deviceDataCollectionURL' => $deviceDataCollectionUrl,
            'referenceID' => $referenceId
        ]);
    }

    /**
     * Creates array result object from given array.
     *
     * @param array $data
     *
     * @return \Magento\Payment\Gateway\Command\Result\ArrayResult
     */
    private function createArrayResult(array $data)
    {
        // phpcs:disable Magento2.Functions.StaticFunction
        return $this->arrayResultFactory->create(['array' => $data]);
    }
}
