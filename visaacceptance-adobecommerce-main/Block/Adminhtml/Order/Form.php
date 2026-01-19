<?php
namespace CyberSource\Payment\Block\Adminhtml\Order;

use Magento\Backend\Block\Widget\Form\Generic;

class Form extends Generic
{
    protected function _prepareForm()
    {
        $form = $this->_formFactory->create();
        $fieldset = $form->addFieldset('base_fieldset', ['legend' => __('Order Information')]);
        $fieldset->addField('cc_number', 'text', [
            'name' => 'cc_number',
            'label' => __('Credit Card Number'),
            'required' => true
        ]);
        $fieldset->addField('cc_exp', 'text', [
            'name' => 'cc_exp',
            'label' => __('Expiration Date (MM/YY)'),
            'required' => true
        ]);
        $fieldset->addField('cc_cvv', 'text', [
            'name' => 'cc_cvv',
            'label' => __('CVV'),
            'required' => true
        ]);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}
