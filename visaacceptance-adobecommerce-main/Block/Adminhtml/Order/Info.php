<?php
namespace CyberSource\Payment\Block\Adminhtml\Order;

use Magento\Backend\Block\Template;

class Info extends Template
{
    public function getOrderInfo()
    {
        // Return order info for display
        return $this->getData('order_info');
    }
}
