<?php
namespace CyberSource\Payment\Block\Adminhtml\Order;

use Magento\Backend\Block\Widget\Form\Container;

class Create extends Container
{
    protected function _construct()
    {
        $this->_objectId = 'order_id';
        $this->_blockGroup = 'CyberSource_Payment';
        $this->_controller = 'adminhtml_order';
        parent::_construct();
        $this->buttonList->update('save', 'label', __('Submit Order'));
    }
}
