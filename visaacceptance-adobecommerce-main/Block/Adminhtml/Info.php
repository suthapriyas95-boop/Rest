<?php

declare(strict_types=1);

namespace CyberSource\Payment\Block\Adminhtml;

class Info extends \Magento\Payment\Block\Info
{
    protected $_template = 'CyberSource_Payment::order/view/payment.phtml';

    public function getMethodCode(): string
    {
        $info = $this->getInfo();
        return $info ? (string)$info->getMethod() : '';
    }

    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $data = [];
        $info = $this->getInfo();

        if ($info) {
            if ($cardType = $info->getAdditionalInformation('cardType')) {
                $data[(string)__('Card Type')] = $cardType;
            }
            if ($maskedPan = $info->getAdditionalInformation('maskedPan')) {
                $data[(string)__('Card Number')] = $maskedPan;
            }
        }

        return $transport->setData(array_merge($data, $transport->getData()));
    }
}
