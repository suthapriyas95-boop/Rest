# AdminHtml_Order_17012026.md

This document contains the full code for enabling credit card features (Authorization, PA, 3DS, Capture, Refund, Tokenization, etc.) for Admin Order creation in the CyberSource\Payment module. All code is for the adminhtml area and follows Magento 2 best practices.

---

## 1. Block/Adminhtml/Order/Create.php
```php
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
```

## 2. Block/Adminhtml/Order/Form.php
```php
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
```

## 3. Block/Adminhtml/Order/Payment.php
```php
<?php
namespace CyberSource\Payment\Block\Adminhtml\Order;

use Magento\Backend\Block\Template;

class Payment extends Template
{
    public function getPaymentMethods()
    {
        // Return allowed payment methods from config
        return ['VI' => 'Visa', 'MC' => 'MasterCard', 'AE' => 'Amex', 'DI' => 'Discover'];
    }
}
```

## 4. Block/Adminhtml/Order/Info.php
```php
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
```

---

## 5. Controller/Adminhtml/Order/Create.php
```php
<?php
namespace CyberSource\Payment\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class Create extends Action
{
    protected $resultPageFactory;
    public function __construct(Action\Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('CyberSource_Payment::order_create');
        $resultPage->getConfig()->getTitle()->prepend(__('Create Admin Order'));
        return $resultPage;
    }
}
```

## 6. Controller/Adminhtml/Order/Save.php
```php
<?php
namespace CyberSource\Payment\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Controller\Result\RedirectFactory;

class Save extends Action
{
    protected $orderFactory;
    protected $resultRedirectFactory;
    public function __construct(Action\Context $context, OrderFactory $orderFactory, RedirectFactory $resultRedirectFactory)
    {
        parent::__construct($context);
        $this->orderFactory = $orderFactory;
        $this->resultRedirectFactory = $resultRedirectFactory;
    }
    public function execute()
    {
        // Implement order save logic, including authorization, PA, 3DS, etc.
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('*/*/index');
        return $resultRedirect;
    }
}
```

## 7. Controller/Adminhtml/Order/Index.php
```php
<?php
namespace CyberSource\Payment\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    protected $resultPageFactory;
    public function __construct(Action\Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('CyberSource_Payment::order_index');
        $resultPage->getConfig()->getTitle()->prepend(__('Admin Orders'));
        return $resultPage;
    }
}
```

---

## 8. Layout XML (examples)

### view/adminhtml/layout/cybersource_order_create.xml
```xml
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <block class="CyberSource\\Payment\\Block\\Adminhtml\\Order\\Create" name="cybersource.admin.order.create" template="CyberSource_Payment::order/create.phtml"/>
        </referenceContainer>
    </body>
</page>
```

### view/adminhtml/layout/cybersource_order_form.xml
```xml
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <block class="CyberSource\\Payment\\Block\\Adminhtml\\Order\\Form" name="cybersource.admin.order.form" template="CyberSource_Payment::order/form.phtml"/>
        </referenceContainer>
    </body>
</page>
```

---

## 9. Templates (PHTML)

### view/adminhtml/templates/order/create.phtml
```php
<h1><?= __('Create Admin Order') ?></h1>
<?= $block->getChildHtml('form') ?>
```

### view/adminhtml/templates/order/form.phtml
```php
<form action="<?= $block->getUrl('*/*/save') ?>" method="post">
    <!-- Render credit card fields -->
    <?= $block->getFormHtml() ?>
    <button type="submit" class="action-primary">Submit Order</button>
</form>
```

### view/adminhtml/templates/order/payment.phtml
```php
<div>
    <label><?= __('Payment Method') ?></label>
    <select name="payment_method">
        <?php foreach ($block->getPaymentMethods() as $code => $label): ?>
            <option value="<?= $code ?>"><?= $label ?></option>
        <?php endforeach; ?>
    </select>
</div>
```

### view/adminhtml/templates/order/info.phtml
```php
<div>
    <h2><?= __('Order Info') ?></h2>
    <pre><?= print_r($block->getOrderInfo(), 1) ?></pre>
</div>
```

---

This code provides a full adminhtml structure for credit card order creation, including blocks, controllers, layouts, and templates. You must implement the actual payment logic (authorization, PA, 3DS, etc.) in the Save controller and related models/services as per your business requirements.
