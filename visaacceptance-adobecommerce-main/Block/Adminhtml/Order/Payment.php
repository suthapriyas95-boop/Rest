<?php
namespace CyberSource\Payment\Block\Adminhtml\Order;

use Magento\Backend\Block\Template;

class Payment extends Template
{
    public function getPaymentMethods()
    {
        // Return allowed payment methods from config or hardcoded for demo
        return ['VI' => 'Visa', 'MC' => 'MasterCard', 'AE' => 'Amex', 'DI' => 'Discover'];
    }
}
