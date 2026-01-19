<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Validator;

class ConversionDetailsValidator extends \Magento\Payment\Gateway\Validator\AbstractValidator
{
    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    /**
     * Constructor
     *
     * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     */
    public function __construct(
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory,
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
    ) {
        parent::__construct($resultFactory);
        $this->subjectReader = $subjectReader;
    }

    /**
     * @inheritDoc
     */
    public function validate(array $validationSubject)
    {

        $result = $this->subjectReader->readResponse($validationSubject);

        if (!isset($result['conversionDetails']) || !is_array($result['conversionDetails'])) {
            return $this->createResult(false, ['conversionDetails is not set.']);
        }

        return $this->createResult(true);
    }
}
