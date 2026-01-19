<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Validator;

class RestResponseCodeValidator extends \Magento\Payment\Gateway\Validator\AbstractValidator
{
    public const RESPONSE_CODE_OK = 200;
    public const RESPONSE_CODE_CREATED = 201;
    public const RESPONSE_CODE_NOT_FOUND = 404;
    public const RESPONSE_CODE_UNAUTHORIZED = 401;
    public const RESPONSE_CODE_BAD_REQUEST = 400;

    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;
    /**
     * @var int
     */
    private $validResponseCode;

    /**
     * RestResponseCodeValidator constructor.
     *
     * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param int $validResponseCode
     */
    public function __construct(
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory,
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        $validResponseCode = self::RESPONSE_CODE_OK
    ) {
        parent::__construct($resultFactory);
        $this->subjectReader = $subjectReader;
        $this->validResponseCode = $validResponseCode;
    }

    /**
     * @inheritDoc
     */
    public function validate(array $validationSubject)
    {

        $result = $this->subjectReader->readResponse($validationSubject);

        $httpCode = $result['http_code'] ?? static::RESPONSE_CODE_BAD_REQUEST;

        if ($httpCode == static::RESPONSE_CODE_NOT_FOUND) {
            throw new NotFoundException('No data found.');
        }

        if ($httpCode != $this->validResponseCode) {
            return $this->createResult(false, [
                __($result['errorInformation']['message'] ?? 'Unable to process your transaction. Please try again later.')
            ]);
        }

        return $this->createResult(true);
    }
}
