<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Command;

use Exception;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Command\Result\ArrayResultFactory;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;

class GenerateCaptureContextCommand implements CommandInterface
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
     * @var ValidatorInterface
     */
    private $validator;
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var HandlerInterface|null
     */
    private $handler;

    /**
     * @param TransferFactoryInterface $transferFactory
     * @param ArrayResultFactory $arrayResultFactory
     * @param BuilderInterface $requestBuilder
     * @param ValidatorInterface $validator
     * @param ClientInterface $client
     * @param HandlerInterface|null $handler
     */
    public function __construct(
        TransferFactoryInterface $transferFactory,
        ArrayResultFactory $arrayResultFactory,
        BuilderInterface $requestBuilder,
        ValidatorInterface $validator,
        ClientInterface $client,
        ?HandlerInterface $handler = null
    ) {
        $this->transferFactory = $transferFactory;
        $this->arrayResultFactory = $arrayResultFactory;
        $this->requestBuilder = $requestBuilder;
        $this->validator = $validator;
        $this->client = $client;
        $this->handler = $handler;
    }

    /**
     * Executes command basing on business logic specified in execute method of the command class.
     *
     * @param array $commandSubject
     * @return ResultInterface
     *
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

        return $this->arrayResultFactory->create(['array' => $response]);
    }
}
