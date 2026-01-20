<?php

declare(strict_types=1);

namespace CyberSource\Payment\Block\Adminhtml\Order\Create\Billing\Method;

class Form extends \Magento\Payment\Block\Form
{
    protected $_template = 'CyberSource_Payment::cybersource/newcard.phtml';

    public function getMethodCode()
    {
        return $this->getMethod()->getCode();
    }
}
