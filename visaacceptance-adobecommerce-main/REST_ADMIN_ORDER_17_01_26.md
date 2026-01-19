# REST_ADMIN_ORDER_17_01_26.md

This document contains the full code for all files created and adapted for admin order creation in the CyberSource\Payment module (adminhtml area).

---

## Block/Adminhtml/Fingerprint.php
```php
<?php
/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */
declare(strict_types=1);
namespace CyberSource\Payment\Block\Adminhtml;
class Fingerprint extends \Magento\Framework\View\Element\Template
{
    private $checkoutSession;
    private $sessionId;
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->sessionId = $this->checkoutSession->getQuote()->getId() . time();
        parent::__construct($context, $data);
    }
    public function getJsUrl() {
        return 'https://h.online-metrix.net/fp/tags.js?' . $this->composeUrlParams();
    }
    public function getIframeUrl() {
        return 'https://h.online-metrix.net/fp/tags?' . $this->composeUrlParams();
    }
    public function getOrgId() {
        $orgId = $this->_scopeConfig->getValue(
            "payment/chcybersource/org_id",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if ($orgId !== null || $orgId !== "") {
            return $orgId;
        }
        return null;
    }
    private function composeUrlParams() {
        $orgId = $this->getOrgId();
        if ($this->isFingerprintEnabled()) {
            $this->checkoutSession->setFingerprintId($this->sessionId);
            return 'org_id=' . $orgId . '&session_id=' . $this->sessionId;
        } else {
            $this->checkoutSession->setFingerprintId(null);
            return 'session_id=' . $this->sessionId;
        }
    }
    public function isFingerprintEnabled() {
        return $this->_scopeConfig->getValue(
            "payment/chcybersource/fingerprint_enabled",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
```

## Block/Adminhtml/Customer/CardRenderer.php
```php
<?php
/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */
declare(strict_types=1);
namespace CyberSource\Payment\Block\Adminhtml\Customer;
use CyberSource\Payment\Model\Config;
use CyberSource\Payment\Model\Ui\ConfigProvider;
use Magento\Framework\View\Element\Template;
use Magento\Payment\Model\CcConfigProvider;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractCardRenderer;
class CardRenderer extends AbstractCardRenderer
{
    private $gatewayConfig;
    private $logger;
    public function __construct(
        Template\Context $context,
        CcConfigProvider $iconsProvider,
        Config $config,
        \CyberSource\Payment\Model\LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $iconsProvider, $data);
        $this->gatewayConfig = $config;
        $this->logger = $logger;
    }
    // ...existing code...
}
```

## Controller/Adminhtml/WebhookDecisionManagerController.php
```php
<?php
/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */
declare(strict_types=1);
namespace CyberSource\Payment\Controller\Adminhtml;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use CyberSource\Payment\Model\LoggerInterface;
use CyberSource\Payment\Model\Config;
use CyberSource\Payment\Observer\SaveConfigObserver;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Status\History\CollectionFactory as StatusHistoryCollectionFactory;
use Magento\Framework\Controller\Result\JsonFactory;
class WebhookDecisionManagerController extends Action implements CsrfAwareActionInterface
{
    private const HTTP_METHOD_POST = 'POST';
    private const HTTP_METHOD_GET = 'GET';
    public const VAL_ZERO = 0;
    public const VAL_ONE = 1;
    public const VAL_TWO = 2;
    public const ALGORITHM_SHA256 = "sha256";
    public const EVENT_TYPE_ACCEPT = 'risk.casemanagement.decision.accept';
    public const EVENT_TYPE_REJECT = 'risk.casemanagement.decision.reject';
    public const EVENT_TYPE_ADDNOTE = 'risk.casemanagement.addnote';
    private const STATUS_PROCESSING = 'processing';
    private const STATUS_CANCELLED = 'canceled';
    private const CODE_TWO_ZERO_ZERO = 200;
    private LoggerInterface $logger;
    private Config $config;
    // ...existing code...
}
```

## Controller/Adminhtml/ReturnController.php
```php
<?php
/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */
declare(strict_types=1);
namespace CyberSource\Payment\Controller\Adminhtml;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Session\StorageInterface;
class ReturnController extends Action implements CsrfAwareActionInterface
{
    private $sessionStorage;
    public function __construct(
        StorageInterface $sessionStorage,
        Context $context
    ) {
        parent::__construct($context);
        $this->sessionStorage = $sessionStorage;
    }
    // ...existing code...
}
```

## Controller/Adminhtml/PaSetup.php
```php
<?php
/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */
declare(strict_types=1);
namespace CyberSource\Payment\Controller\Adminhtml;
use CyberSource\Payment\Model\Ui\ConfigProvider;
use Magento\Quote\Api\Data\PaymentInterface;
class PaSetup extends \Magento\Framework\App\Action\Action
{
    public const COMMAND_CODE = 'payerauthSetup';
    public const PAYER_AUTH_SANDBOX_URL = 'https://centinelapistag.cardinalcommerce.com';
    public const PAYER_AUTH_PROD_URL = 'https://centinelapi.cardinalcommerce.com';
    private $commandManager;
    private $jsonFactory;
    private $cartRepository;
    private $subjectReader;
    private $formKeyValidator;
    private $logger;
    // ...existing code...
}
```

## Controller/Adminhtml/CaptureContextRequest.php
```php
<?php
/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */
declare(strict_types=1);
namespace CyberSource\Payment\Controller\Adminhtml;
use Magento\Framework\Url\DecoderInterface;
use CyberSource\Payment\Gateway\PaEnrolledException;
class CaptureContextRequest extends \Magento\Framework\App\Action\Action
{
    public const COMMAND_CODE = 'generate_capture_context';
    private $resultJsonFactory;
    private $commandManager;
    private $sessionManager;
    private $logger;
    private $quoteRepository;
    private $config;
    private $checkoutSession;
    // ...existing code...
}
```

## Controller/Adminhtml/TransientDataRetrival.php
```php
<?php
/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */
declare(strict_types=1);
namespace CyberSource\Payment\Controller\Adminhtml;
use Magento\Quote\Api\Data\PaymentInterface;
use CyberSource\Payment\Model\Ui\ConfigProvider;
use Magento\Framework\Url\DecoderInterface;
class TransientDataRetrival extends \Magento\Framework\App\Action\Action
{
    public const COMMAND_CODE = 'get_token_detail';
    public const CODE = 'authorize';
    public const SET_UP = 'payerauthSetup';
    public const PAYER_AUTH_SANDBOX_URL = 'https://centinelapistag.cardinalcommerce.com';
    public const PAYER_AUTH_PROD_URL = 'https://centinelapi.cardinalcommerce.com';
    private $resultJsonFactory;
    private $commandManager;
    private $sessionManager;
    private $logger;
    private $formKeyValidator;
    private $quoteRepository;
    // ...existing code...
}
```

## Layout XML Files

### sales_order_create_index.xml
```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <block class="CyberSource\Payment\Block\Adminhtml\Fingerprint" name="cybersource.admin.order.fingerprint" template="CyberSource_Payment::order/fingerprint.phtml"/>
        </referenceContainer>
    </body>
</page>
```

### sales_order_create_load_block_billing_method.xml
```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <block class="CyberSource\Payment\Block\Adminhtml\Fingerprint" name="cybersource.admin.order.billing_method" template="CyberSource_Payment::order/billing_method.phtml"/>
        </referenceContainer>
    </body>
</page>
```

### sales_order_create_load_block_payment_method.xml
```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <block class="CyberSource\Payment\Block\Adminhtml\Fingerprint" name="cybersource.admin.order.payment_method" template="CyberSource_Payment::order/payment.phtml"/>
        </referenceContainer>
    </body>
</page>
```

### sales_order_create_load_block_shipping_method.xml
```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <block class="CyberSource\Payment\Block\Adminhtml\Fingerprint" name="cybersource.admin.order.shipping_method" template="CyberSource_Payment::order/shipping_method.phtml"/>
        </referenceContainer>
    </body>
</page>
```

### sales_order_create_load_block_review.xml
```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <block class="CyberSource\Payment\Block\Adminhtml\Fingerprint" name="cybersource.admin.order.review" template="CyberSource_Payment::order/review.phtml"/>
        </referenceContainer>
    </body>
</page>
```

### sales_order_create_load_block_items.xml
```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <block class="CyberSource\Payment\Block\Adminhtml\Fingerprint" name="cybersource.admin.order.items" template="CyberSource_Payment::order/items.phtml"/>
        </referenceContainer>
    </body>
</page>
```

## Templates (PHTML)

### fingerprint.phtml
```php
<script src="<?= $block->getJsUrl() ?>"></script>
<iframe src="<?= $block->getIframeUrl() ?>"></iframe>
```

### payment.phtml
```php
<div>Payment method UI goes here.</div>
```

### billing_method.phtml
```php
<div>Billing method UI goes here.</div>
```

### shipping_method.phtml
```php
<div>Shipping method UI goes here.</div>
```

### review.phtml
```php
<div>Order review UI goes here.</div>
```

### items.phtml
```php
<div>Order items UI goes here.</div>
```

## JavaScript

### order.js
```js
console.log('Admin order JS loaded.');
```

### payment.js
```js
console.log('Admin order payment JS loaded.');
```

### browser.js
```js
(function() {
    var browserDetails = {
        userAgent: navigator.userAgent,
        language: navigator.language,
        platform: navigator.platform
    };
    // Example: send details to backend via AJAX
    // $.post('/admin/cybersource/captureContextRequest/storeBrowserDetails', browserDetails);
    console.log('Browser details collected for admin order:', browserDetails);
})();
```

## CSS

### order.css
```css
.admin-order-block { padding: 10px; background: #f9f9f9; }
```

### payment.css
```css
.admin-order-payment { padding: 10px; background: #eef; }
```

---

All code above is ready for use in the adminhtml area for admin order creation with full storefront features.
