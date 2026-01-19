<?php

declare(strict_types=1);

namespace CyberSource\Payment\Controller\Adminhtml;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use CyberSource\Payment\Model\LoggerInterface;
use CyberSource\Payment\Model\Config;
use CyberSource\Payment\Observer\SaveConfigObserver;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Status\History\CollectionFactory as StatusHistoryCollectionFactory;
use Magento\Framework\Controller\Result\JsonFactory;

class WebhookDecisionManagerController extends \Magento\Backend\App\Action implements CsrfAwareActionInterface
{
    private const HTTP_METHOD_POST = 'POST';
    private const HTTP_METHOD_GET = 'GET';
    public const VAL_ZERO = 0;
    public const VAL_ONE = 1;
    public const VAL_TWO = 2;
    public const ALGORITHM_SHA256 = "sha256";
    public const EVENT_TYPE_ACCEPT = 'risk.casemanagement.decision.accept';
    public const EVENT_TYPE_REJECT = 'risk.casemanagement.decision.reject';
    public const EVENT_TYPE_ADDNOTE = 'risk.casemanagement.addnote';
    private const STATUS_PROCESSING = 'processing';
    private const STATUS_CANCELLED = 'canceled';
    private const CODE_TWO_ZERO_ZERO = 200;

    private LoggerInterface $logger;
    private Config $config;
    private SaveConfigObserver $saveConfigObserver;
    private JsonFactory $resultJsonFactory;
    private OrderCollectionFactory $orderCollectionFactory;
    private $statusHistoryCollectionFactory;
    private $checkoutSession;
    private $orderFactory;
    private $sessionStorage;
    private $orderRepository;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        LoggerInterface $logger,
        Config $config,
        SaveConfigObserver $saveConfigObserver,
        JsonFactory $resultJsonFactory,
        OrderCollectionFactory $orderCollectionFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        StatusHistoryCollectionFactory $statusHistoryCollectionFactory,
        \Magento\Framework\Session\StorageInterface $sessionStorage,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->config = $config;
        $this->saveConfigObserver = $saveConfigObserver;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->statusHistoryCollectionFactory = $statusHistoryCollectionFactory;
        $this->sessionStorage = $sessionStorage;
        $this->orderRepository = $orderRepository;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $quote = $this->checkoutSession->getQuote();

        try {
            if ($this->isPostRequest()) {
                $this->handlePostRequest($result);
            } elseif ($this->isGetRequest()) {
                $this->handleGetRequest($result);
            } else {
                $this->handleInvalidMethod($result);
            }
        } catch (\InvalidArgumentException $e) {
            $this->handleInvalidArgument($result, $e);
        } catch (\Exception $e) {
            $this->handleGeneralException($result, $e);
        }
        return $result;
    }

    private function isPostRequest(): bool
    {
        return self::HTTP_METHOD_POST === $this->getRequest()->getMethod();
    }

    private function isGetRequest(): bool
    {
        return self::HTTP_METHOD_GET === $this->getRequest()->getMethod();
    }

    private function handlePostRequest($result): void
    {
        $decisionManagerEnabled = $this->config->getDecisionManagerEnabled();
        if ($decisionManagerEnabled == self::VAL_ONE) {
            $decisionManagerData = json_decode(file_get_contents('php://input'));
            $dmData = json_decode(file_get_contents('php://input'), true);
            $headers = $this->getRequest()->getHeaders('v-c-signature');
            $merchantId = $this->config->getMerchantId();
            $webhookDetails = $this->saveConfigObserver->storedWebhookDetails($merchantId);
            $referenceNumber = $decisionManagerData->payload->data->referenceNumber;
            $payload = $dmData["payload"];
            if ($this->isValidRequest($decisionManagerData, $merchantId, $webhookDetails)) {
                $webhookDetails = $this->saveConfigObserver->queryWebhookDetails($merchantId);
                if (!empty($webhookDetails)) {
                    $notificationValidation = $this->notificationValidation($headers->getFieldValue(), $payload, $webhookDetails);
                    if ($notificationValidation === true) {
                        if ($this->isReferenceNumberValid($referenceNumber)) {
                            $this->processEventType($decisionManagerData, $referenceNumber, $result);
                        } else {
                            $this->logger->error('Reference number is not valid', ['referenceNumber' => $referenceNumber]);
                            $result->setData(['message' => 'Reference number is not valid.']);
                        }
                    } else {
                        $this->logger->error('Notification validation failed', ['referenceNumber' => $referenceNumber]);
                        $result->setData(['message' => 'Notification validation failed.']);
                    }
                } else {
                    $this->logger->error('Webhook details not found', ['merchantId' => $merchantId]);
                    $result->setData(['message' => 'Webhook details not found.']);
                }
            } else {
                $this->validateRequestData($decisionManagerData, $merchantId, $webhookDetails, $result);
            }
        } else {
            $this->logger->info('Decision manager not enabled.');
            $result->setHttpResponseCode(400);
            $result->setData(['message' => 'Decision manager is not enabled']);
        }
    }

    private function handleGetRequest($result): void
    {
        $this->logger->info('GET request received.');
        $result->setHttpResponseCode(self::CODE_TWO_ZERO_ZERO);
        $result->setData(['message' => 'GET request received.']);
    }

    private function handleInvalidMethod($result): void
    {
        $this->logger->info('Invalid HTTP method.');
        $result->setHttpResponseCode(405);
        $result->setData(['message' => 'Invalid HTTP method.']);
    }

    private function handleInvalidArgument($result, \InvalidArgumentException $e): void
    {
        $this->logger->error('Invalid argument: ' . $e->getMessage());
        $result->setHttpResponseCode(400);
        $result->setData(['message' => 'Invalid argument: ' . $e->getMessage()]);
    }

    private function handleGeneralException($result, \Exception $e): void
    {
        $this->logger->error('Error processing webhook Decision Manager', ['exception' => $e]);
        $result->setHttpResponseCode(500);
        $result->setData(['message' => 'Error processing Decision Manager', 'error' => $e->getMessage()]);
    }

    private function processEventType($decisionManagerData, string $referenceNumber, $result): void
    {
        if ($decisionManagerData->eventType == self::EVENT_TYPE_ACCEPT) {
            $this->updateOrderStatus($referenceNumber, self::STATUS_PROCESSING);
            $this->logger->info('Order status updated to processing', ['referenceNumber' => $referenceNumber]);
            $result->setData(['message' => 'Order status updated to processing']);
        } elseif ($decisionManagerData->eventType == self::EVENT_TYPE_REJECT) {
            $this->updateOrderStatus($referenceNumber, self::STATUS_CANCELLED);
            $this->logger->info('Order status updated to cancelled', ['referenceNumber' => $referenceNumber]);
            $result->setData(['message' => 'Order status updated to canceled']);
        } elseif ($decisionManagerData->eventType == self::EVENT_TYPE_ADDNOTE) {
            $comment = $decisionManagerData->payload->data->notes[1]->comment;
            $this->updateAdditionalInformation($referenceNumber, $comment);
            $this->logger->info('notes updated');
            $result->setData(['message' => 'notes updated']);
        } else {
            $this->logger->error('Invalid event type: ' . $decisionManagerData->eventType . ', expected: ' . self::EVENT_TYPE_ACCEPT . ' or ' . self::EVENT_TYPE_REJECT . ' or ' . self::EVENT_TYPE_ADDNOTE);
            $result->setData(['message' => 'Invalid event type']);
        }
        $result->setHttpResponseCode(self::CODE_TWO_ZERO_ZERO);
    }

    private function validateRequestData($decisionManagerData, string $merchantId, array $webhookDetails, $result): void
    {
        $this->logger->info('Invalid request payload', [
            'organizationId' => $decisionManagerData->organizationId,
            'merchantId' => $merchantId,
            'webhookId' => $decisionManagerData->webhookId,
            'expectedWebhookId' => $webhookDetails['webhook_id'],
            'productId' => $decisionManagerData->productId,
            'expectedProductId' => $webhookDetails['product_id']
        ]);
        $result->setHttpResponseCode(400);
        $result->setData(['message' => 'Invalid DM request payload received.']);
    }

    private function isValidRequest($decisionManagerData, string $merchantId, array $webhookDetails): bool
    {
        if (!is_object($decisionManagerData)) {
            return false;
        }

        return (
            $decisionManagerData->organizationId == $merchantId &&
            $webhookDetails['webhook_id'] == $decisionManagerData->webhookId &&
            $webhookDetails['product_id'] == $decisionManagerData->productId
        );
    }

    private function isReferenceNumberValid(string $referenceNumber): bool
    {
        $orderCollection = $this->orderCollectionFactory->create()->addFieldToFilter('increment_id', $referenceNumber);

        return $orderCollection->getSize() > 0;
    }

    private function updateOrderStatus(string $referenceNumber, string $status): void
    {
        $orderCollection = $this->orderCollectionFactory->create()->addFieldToFilter('increment_id', $referenceNumber);

        if ($orderCollection->getSize() > 0) {
            $order = $orderCollection->getFirstItem();
            $order->setStatus($status);
            $order->setState($status);
            $order->save();
            $this->logger->info('Order status updated', ['referenceNumber' => $referenceNumber, 'status' => $status]);
        } else {
            $this->logger->info('Reference number not found in the database', ['referenceNumber' => $referenceNumber]);
        }
    }

    private function updateAdditionalInformation($referenceNumber, $comment): void
    {
        $order = $this->orderFactory->create()->loadByIncrementId($referenceNumber);
        $orderCollection = $this->statusHistoryCollectionFactory->create()->addFieldToFilter('parent_id', $order->getEntityId());

        if ($orderCollection->getSize() > 0) {
            $order = $orderCollection->getFirstItem();
            $currentNote = $order->getComment();
            $comments = [];
            if ($currentNote) {
                $comments = explode("\n", $currentNote);
            }
            $comments[] = $comment;
            $updatedNote = implode("\n", $comments);
            $order->setComment($updatedNote);
            $order->save();
        }
    }

    public function notificationValidation(string $digital_signature, array $payload, array $webhook_details): bool
    {
        $response = false;
        if (!empty($digital_signature) && !empty($payload) && !empty($webhook_details)) {
            $signature_data = $this->splitSignature($digital_signature);
            if (isset($signature_data['time_stamp']) && isset($signature_data['key_id']) && isset($signature_data['signature'])) {
                $time_stamped_payload = $signature_data['time_stamp'] . "." . json_encode($payload, JSON_UNESCAPED_SLASHES);
                $digital_signature_key = ($webhook_details['digital_signature_key_id'] === $signature_data['key_id']) ? $webhook_details['digital_signature_key'] : "null";
                if (isset($digital_signature_key)) {
                    $decode_key = base64_decode($digital_signature_key);
                    $signature = base64_encode(hash_hmac(self::ALGORITHM_SHA256, $time_stamped_payload, $decode_key, true));
                    if (hash_equals($signature, $signature_data['signature'])) {
                        $response = true;
                    }
                }
            }
        }
        return $response;
    }

    public function splitSignature(string $digital_signature): array
    {
        $signature_data = [];
        $signature_parts = explode(";", $digital_signature);
        $signature_data['time_stamp'] = trim(explode("t=", $signature_parts[self::VAL_ZERO])[self::VAL_ONE]);
        $signature_data['key_id'] = trim(explode("keyId=", $signature_parts[self::VAL_ONE])[self::VAL_ONE]);
        $signature_data['signature'] = trim(explode("sig=", $signature_parts[self::VAL_TWO])[self::VAL_ONE]);
        return $signature_data;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
