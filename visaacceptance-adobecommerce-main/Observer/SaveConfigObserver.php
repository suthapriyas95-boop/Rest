<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Observer;

use CyberSource\Payment\Model\Config;
use CyberSource\Payment\Model\Webhook;
use CyberSource\Payment\Model\ResourceModel\Webhook\Collection;
use Magento\Store\Model\StoreManagerInterface;
use CyberSource\Payment\Model\LoggerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Session\SessionManagerInterface;

/**
 * Class SaveConfigObserver
 *
 * Observer for saving configuration settings related to CyberSource payment.
 */
class SaveConfigObserver implements ObserverInterface
{
    public const COMMAND_GET_EVENT_DETAIL = 'get_event_detail';
    public const COMMAND_CREATE_WEBHOOK_SECURITY_KEYS = 'create_webhook_security_keys';
    public const COMMAND_CREATE_WEBHOOK = 'create_webhook';
    public const RISK_CASEMANAGEMENT_DECISIONS = [
        'risk.casemanagement.decision.accept',
        'risk.casemanagement.decision.reject',
        'risk.casemanagement.addnote'
    ];
    public const HTTP_CODE_OK = 200;
    public const HTTP_CODE_CREATED = 201;
    public const HTTP_CODE_NOT_FOUND = 404;
    public const VALUE_ZERO = 0;
    public const PRODUCT_DECISION_MANAGER = 'decisionManager';
    public const COMMAND_GET_SINGLE_WEBHOOK_DETAILS = 'get_single_webhook_details';
    public const COMMAND_GET_ALL_CREATED_WEBHOOKS = 'get_all_created_webhook';
    public const COMMAND_DELETE_WEBHOOK_SUBSCRIPTION = 'delete_webhook_subscription';
    public const VALUE_NULL = '';

    /**
     * @var \Magento\Payment\Gateway\Command\CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Webhook
     */
    private $webhookModel;

    /**
     * @var Collection
     */
    private $webhookCollection;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

      /**
       * @var SessionManagerInterface
       */
      private $session;


    /**
     * SaveConfigObserver constructor.
     *
     * @param \Magento\Payment\Gateway\Command\CommandManagerInterface $commandManager
     * @param Config $config
     * @param Webhook $webhookModel
     * @param Collection $webhookCollection
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        \Magento\Payment\Gateway\Command\CommandManagerInterface $commandManager,
        Config $config,
        Webhook $webhookModel,
        Collection $webhookCollection,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        SessionManagerInterface $session
    ) {
        $this->commandManager = $commandManager;
        $this->config = $config;
        $this->webhookModel = $webhookModel;
        $this->webhookCollection = $webhookCollection;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->session = $session;
    }

    /**
     * Execute observer method.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $storeId = $this->storeManager->getStore()->getId();
        $merchantId = $this->config->getMerchantId($storeId);
        $decisionManagerEnabled = $this->config->getDecisionManagerEnabled();

        if ($decisionManagerEnabled) {
            $productListResponse = $this->requestAndVerifyWebhookProductList(
                self::PRODUCT_DECISION_MANAGER,
                self::RISK_CASEMANAGEMENT_DECISIONS
            );
            if ($productListResponse['status']) {
                $subscriptionCreationRequired = $this->verifySubscriptionCreationRequired(
                    self::PRODUCT_DECISION_MANAGER,
                    self::RISK_CASEMANAGEMENT_DECISIONS,
                    $merchantId
                );
                if ($subscriptionCreationRequired['status']) {
                    $digitalSignature = $this->generateWebhookDigitalSignatureKey();
                    if ($digitalSignature['status']) {
                        $subscriptionDetails = $this->createWebhookSubscription();
                        if ($subscriptionDetails['status']) {
                            $webhookDetails = [
                                'webhook_id' => $subscriptionDetails['webhook_id'],
                                'digital_signature_key_id' => $digitalSignature['key_id'],
                                'digital_signature_key' => $digitalSignature['key'],
                                'product_id' => self::PRODUCT_DECISION_MANAGER,
                                'organization_id' => $merchantId
                            ];
                            $isInsertionSuccess = $this->insertWebhookDetails($webhookDetails);
                            if ($isInsertionSuccess) {
                                $this->logger->info(
                                    "Webhook subscription data successfully inserted into the database."
                                );
                            } else {
                                $this->logger->info(
                                    "Warning: Unable to process your webhook subscription creation request."
                                    . " An error occurred while storing data into webhook table."
                                );
                            }
                        } else {
                            $this->logger->info($subscriptionDetails['warning_message']);
                        }
                    } else {
                        $this->logger->info($digitalSignature['warning_message']);
                    }
                } else {
                    $this->logger->info(
                        $subscriptionCreationRequired['warning']
                        ?? 'Warning: Unable to process your webhook subscription creation request. '
                        . 'Please verify the configuration and try again.'
                    );
                }
            } else {
                $this->logger->info($productListResponse['warning_message']);
            }
        }
    }

    /**
     * Check if events exist in the provided arrays.
     *
     * @param array $allExistingEvents
     * @param array $requestingEvents
     * @return bool
     */
    public function isEventsExists(array $allExistingEvents, array $requestingEvents): bool
    {
        if (empty($allExistingEvents) || empty($requestingEvents)) {
            return false;
        }

        foreach ($requestingEvents as $eventType) {
            if (!in_array($eventType, $allExistingEvents)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Request and verify webhook product list.
     *
     * @param string $productId
     * @param array $requiredEventTypes
     * @return array
     */
    public function requestAndVerifyWebhookProductList(string $productId, array $requiredEventTypes): array
    {
        $responseEventTypes = [];
        $response = [
            'status' => false,
            'warning_message' => 'Warning: Unable to fetch list of products and event types'
        ];

        $commandResult = $this->commandManager->executeByCode(self::COMMAND_GET_EVENT_DETAIL);
        $apiResponse = $commandResult->get();

        if (self::HTTP_CODE_OK === $apiResponse['http_code']) {
            unset($apiResponse['http_code'], $apiResponse['http_message']);
            foreach ($apiResponse as $productList) {
                if ($productList['productId'] === $productId) {
                    foreach ($productList['eventTypes'] as $eventNames) {
                        $responseEventTypes[] = $eventNames['eventName'];
                    }
                }
            }

            if ($this->isEventsExists($responseEventTypes, $requiredEventTypes)) {
                $response['status'] = true;
            } else {
                $response['warning_message'] = 'Warning: Required events not found';
            }
        }

        return $response;
    }

    /**
     * Generate webhook digital signature key.
     *
     * @return array
     */
    public function generateWebhookDigitalSignatureKey(): array
    {
        $response = [
            'status' => false,
            'warning_message' => 'Warning: Unable to generate Webhook Digital Signature Key'
        ];

        $apiResponse = $this->commandManager->executeByCode(self::COMMAND_CREATE_WEBHOOK_SECURITY_KEYS)->get();

        if (self::HTTP_CODE_CREATED === $apiResponse['http_code']) {
            if (isset($apiResponse['keyInformation']['key'], $apiResponse['keyInformation']['keyId'])) {
                $response['status'] = true;
                $response['key'] = $apiResponse['keyInformation']['key'];
                $response['key_id'] = $apiResponse['keyInformation']['keyId'];
            } else {
                $response['warning_message'] = 'Warning: Digital signature key and key id not found in response';
            }
        }

        return $response;
    }

    /**
     * Create webhook subscription.
     *
     * @return array
     */
    public function createWebhookSubscription(): array
    {
        $response = [
            'status' => false,
            'warning_message' => 'Warning: Unable to create webhook subscription'
        ];

        $apiResponse = $this->commandManager->executeByCode(self::COMMAND_CREATE_WEBHOOK)->get();

        if (self::HTTP_CODE_CREATED === $apiResponse['http_code']) {
            if (isset($apiResponse['webhookId'], $apiResponse['organizationId'])) {
                $response['status'] = true;
                $response['webhook_id'] = $apiResponse['webhookId'];
                $response['organization_id'] = $apiResponse['organizationId'];
            } else {
                $response['warning_message'] = 'Warning: WebhookId not found for webhook subscription';
            }
        }

        return $response;
    }

    /**
     * Verify if subscription creation is required.
     *
     * @param string $productId
     * @param array $eventTypes
     * @param string $merchantId
     * @return array
     */
    public function verifySubscriptionCreationRequired(string $productId, array $eventTypes, string $merchantId): array
    {
        $response = [
            'status' => false,
            'warning' => self::VALUE_NULL
        ];

        $storedWebhookDetails = $this->storedWebhookDetails($merchantId);

        if (!empty($storedWebhookDetails) && isset($storedWebhookDetails['webhook_id'])) {
            $webhookDetailsApiResponse = $this->getDetailsOfSingleWebhook();

            if (self::HTTP_CODE_OK === $webhookDetailsApiResponse['http_code']) {
                $organizationIdVerification = $webhookDetailsApiResponse['organizationId'] ?? false;
                $productIdVerification = isset($webhookDetailsApiResponse['productId'])
                    && $organizationIdVerification
                    ? ($webhookDetailsApiResponse['productId'] === $productId)
                    : false;
                $eventTypesVerification = isset($webhookDetailsApiResponse['eventTypes'])
                    && $productIdVerification
                    ? $this->isEventsExists($webhookDetailsApiResponse['eventTypes'], $eventTypes)
                    : false;
                if ($eventTypesVerification) {
                    $response['warning'] = 'Message: Subscription already exists for requesting events';
                } else {
                    $response['status'] = $this->deleteSubscriptionDetails(
                        $merchantId,
                        $productId,
                        $eventTypes,
                        $storedWebhookDetails['webhook_id']
                    );
                }
            } elseif (self::HTTP_CODE_NOT_FOUND === $webhookDetailsApiResponse['http_code']) {
                $response['status'] = $this->deleteSubscriptionDetails(
                    $merchantId,
                    $productId,
                    $eventTypes,
                    $storedWebhookDetails['webhook_id']
                );
            }
        } else {
            $response['status'] = $this->deleteSubscriptionForEachEvent(
                $merchantId,
                $productId,
                $eventTypes
            );
        }

        return $response;
    }

    /**
     * Get details of a single webhook.
     *
     * @return array
     */
    public function getDetailsOfSingleWebhook(): array
    {
        return $this->commandManager->executeByCode(self::COMMAND_GET_SINGLE_WEBHOOK_DETAILS)->get();
    }

    /**
     * Get stored webhook details for a merchant.
     *
     * @param string $merchantId
     * @return array
     */
    public function storedWebhookDetails($merchantId): array
    {
        $webhookId = [];
        $webhookCollection = $this->webhookModel->getCollection()
            ->addFieldToFilter('organization_id', $merchantId)
            ->addFieldToFilter('product_id', self::PRODUCT_DECISION_MANAGER);

        if ($webhookCollection->getSize() > 0) {
            $webhookId['webhook_id'] = $webhookCollection->getFirstItem()->getWebhookId();
            $webhookId['product_id'] = $webhookCollection->getFirstItem()->getProductId();
            return $webhookId;
        } else {
            return [null];
        }
    }

    /**
     * Query webhook details for a merchant.
     *
     * @param string $merchantId
     * @return array
     */
    public function queryWebhookDetails($merchantId)
    {
        $webhookId = [];
        $webhookCollection = $this->webhookModel->getCollection()
            ->addFieldToFilter('organization_id', $merchantId)
            ->addFieldToFilter('product_id', self::PRODUCT_DECISION_MANAGER);

        if ($webhookCollection->getSize() > 0) {
            $webhookId['webhook_id'] = $webhookCollection->getFirstItem()->getWebhookId();
            $webhookId['digital_signature_key_id'] = $webhookCollection->getFirstItem()->getDigitalSignatureKeyId();
            $webhookId['digital_signature_key'] = $webhookCollection->getFirstItem()->getDigitalSignatureKey();

            return $webhookId;
        } else {
            return [null];
        }
    }

    /**
     * Delete stored webhook details.
     *
     * @param string $webhookId
     * @return bool
     */
    public function deleteStoredWebhookDetails($webhookId)
    {
        $response = false;

        $webhookCollection = $this->webhookModel->getCollection()
            ->addFieldToFilter('webhook_id', $webhookId);

        if ($webhookCollection->getSize() > 0) {
            $webhookItem = $webhookCollection->getFirstItem();
            $webhookItem->delete();
            $response = true;
        }
        return $response;
    }

    /**
     * Insert webhook details into the database.
     *
     * @param array $webhookData
     * @return bool|null
     */
    public function insertWebhookDetails(array $webhookData): ?bool
    {
        $returnResponse = false;
        if (!empty($webhookData) && is_array($webhookData)) {
            $webhook = $this->webhookModel;
            $webhook->setData([
                'product_id' => $webhookData['product_id'],
                'organization_id' => $webhookData['organization_id'],
                'webhook_id' => $webhookData['webhook_id'],
                'digital_signature_key_id' => $webhookData['digital_signature_key_id'],
                'digital_signature_key' => $webhookData['digital_signature_key']
            ]);
            $webhook->save();
            $returnResponse = true;
        }
        return $returnResponse;
    }

    /**
     * Delete subscription details.
     *
     * @param string $merchantId
     * @param string $productId
     * @param array $eventTypes
     * @param string $webhookId
     * @return bool
     */
    public function deleteSubscriptionDetails(
        string $merchantId,
        string $productId,
        array $eventTypes,
        string $webhookId
    ): bool {
        $response = false;
        $deleteEventResponse = $this->deleteSubscriptionForEachEvent(
            $merchantId,
            $productId,
            $eventTypes
        );
        if ($deleteEventResponse) {
            $isDeletionSuccess = $this->deleteStoredWebhookDetails($webhookId);
            if ($isDeletionSuccess) {
                $response = true;
            }
        }
        return $response;
    }

    /**
     * Delete subscription for each event.
     *
     * @param string $merchantId
     * @param string $productId
     * @param array $eventTypes
     * @return bool
     */
    public function deleteSubscriptionForEachEvent(
        string $merchantId,
        string $productId,
        array $eventTypes
    ): bool {
        $flag = false;
        foreach ($eventTypes as $eventType) {
            $apiResponse = $this->getAllCreatedWebhooks($eventType);

            if (self::HTTP_CODE_OK === $apiResponse['http_code']) {
                $organizationIdVerification = isset($apiResponse[0]['organizationId'])
                    ? $apiResponse[0]['organizationId'] === $merchantId
                    : false;
                $productIdVerification = (isset($apiResponse[0]['productId']) && $organizationIdVerification)
                    ? $apiResponse[0]['productId'] === $productId
                    : false;
                $eventTypesVerification = (isset($apiResponse[0]['eventTypes'])
                    && is_array($apiResponse[0]['eventTypes'])
                    && $productIdVerification)
                    ? $apiResponse[0]['eventTypes'][self::VALUE_ZERO] === $eventType
                    : false;
                if (isset($apiResponse[0]['webhookId']) && $eventTypesVerification) {
                    $this->session->setData('webhookid_from_all_webhook_request', $apiResponse[0]['webhookId']);
                    $deleteServiceApiResponse = $this->deleteWebhookSubscription();

                    if (self::HTTP_CODE_OK === $deleteServiceApiResponse['http_code'] ||
                        self::HTTP_CODE_NOT_FOUND === $deleteServiceApiResponse['http_code']
                    ) {
                        $flag = true;
                    } else {
                        $flag = false;
                    }
                }
            } elseif (self::HTTP_CODE_NOT_FOUND === $apiResponse['http_code']) {
                $flag = true;
            } else {
                $flag = false;
            }
        }

        return $flag;
    }

    /**
     * Get all created webhooks for an event type.
     *
     * @param string $eventType
     * @return array
     */
    public function getAllCreatedWebhooks($eventType): array
    {
        $apiResponse = $this->commandManager->executeByCode(self::COMMAND_GET_ALL_CREATED_WEBHOOKS)->get();
        return $apiResponse;
    }

    /**
     * Delete webhook subscription.
     *
     * @return array
     */
    public function deleteWebhookSubscription(): array
    {
        $apiResponse = $this->commandManager->executeByCode(self::COMMAND_DELETE_WEBHOOK_SUBSCRIPTION)->get();
        return $apiResponse;
    }
}
