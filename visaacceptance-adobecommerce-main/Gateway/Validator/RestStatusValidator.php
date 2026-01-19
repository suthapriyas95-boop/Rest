<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Validator;

use CyberSource\Payment\Model\Logger;

/**
 * Validates the status when HTTP Response Code is 201 for the transaction
 */
class RestStatusValidator extends \Magento\Payment\Gateway\Validator\AbstractValidator
{
    public const REJECT_PAYMENT_STATUS = ['INVALID_REQUEST', 'DECLINED', 'SERVER_ERROR','AUTHORIZED_RISK_DECLINED'];
    public const RESPONSE_CODE_CREATED = 201;
    public const DM_REVIEW_STATUS = ['AUTHORIZED_PENDING_REVIEW', 'PENDING_REVIEW'];
    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * RestStatusValidator constructor.
     *
     * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param Logger $logger
     */
    public function __construct(
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory,
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        Logger $logger
    ) {
        parent::__construct($resultFactory);
        $this->subjectReader = $subjectReader;
        $this->logger = $logger;
    }

    /**
     * Validates the status when HTTP Response Code is 201 for the transaction
     *
     * @param array $validationSubject
     * @return \Magento\Payment\Gateway\Validator\ResultInterface
     */
    public function validate(array $validationSubject)
    {
        $result = $this->subjectReader->readResponse($validationSubject);

        if (empty($result)) {
            $error_message = __("Response does not exist.");
            return $this->createResult(false, [$error_message]);
        }

        if ($this->isSuccessfulTransaction($result)) {
            return $this->createResult(true, []);
        }

        if ($result['http_code'] == RestResponseCodeValidator::RESPONSE_CODE_CREATED) {
            if (in_array($result['status'], self::REJECT_PAYMENT_STATUS)) {
                $error_message = __(
                    'Sorry your order could not be processed at this time. Reason is %1',
                    $result['errorInformation']['reason']
                );
                $log = __("Transaction is declined due to %1", $result['errorInformation']['reason']);
                $this->logger->critical($log);
                return $this->createResult(false, [$error_message]);
            }
        }

        return $this->createResult(true);
    }

    /**
     * Determines if the transaction is successful
     *
     * @param array $result
     * @return bool
     */
    private function isSuccessfulTransaction(array $result)
    {
        $status = $result['status'];
        $httpCode = $result['http_code'];
        return ($httpCode === self::RESPONSE_CODE_CREATED && in_array($status, self::DM_REVIEW_STATUS));
    }
}
