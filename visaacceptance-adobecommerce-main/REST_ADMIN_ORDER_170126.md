# REST_ADMIN_ORDER_170126.md

This document lists all the code files required to enable full storefront features for Admin Order creation in the CyberSource\Payment module, including browser details collection and session storage.

---

## 1. Block Classes
- Block/Adminhtml/Fingerprint.php
- Block/Adminhtml/Customer/CardRenderer.php

## 2. Controller Classes
- Controller/Adminhtml/CaptureContextRequest.php
- Controller/Adminhtml/PaSetup.php
- Controller/Adminhtml/ReturnController.php
- Controller/Adminhtml/TransientDataRetrival.php
- Controller/Adminhtml/WebhookDecisionManagerController.php

## 3. Layout XML Files
- view/adminhtml/layout/sales_order_create_index.xml
- view/adminhtml/layout/sales_order_create_load_block_billing_method.xml
- view/adminhtml/layout/sales_order_create_load_block_payment_method.xml
- view/adminhtml/layout/sales_order_create_load_block_shipping_method.xml
- view/adminhtml/layout/sales_order_create_load_block_review.xml
- view/adminhtml/layout/sales_order_create_load_block_items.xml

## 4. Templates (PHTML)
- view/adminhtml/templates/order/fingerprint.phtml
- view/adminhtml/templates/order/payment.phtml
- view/adminhtml/templates/order/billing_method.phtml
- view/adminhtml/templates/order/shipping_method.phtml
- view/adminhtml/templates/order/review.phtml
- view/adminhtml/templates/order/items.phtml

## 5. JavaScript
- view/adminhtml/web/js/order.js
- view/adminhtml/web/js/payment.js
- view/adminhtml/web/js/browser.js

## 6. CSS
- view/adminhtml/web/css/order.css
- view/adminhtml/web/css/payment.css

## 7. Helper/Model/Service
- Helper/Data.php
- Model/Payment.php

## 8. etc/adminhtml/routes.xml
- etc/adminhtml/routes.xml

## 9. Observer/Plugin (if needed)
- Observer/SubmitObserver.php
- Plugin/Checkout/Controller/AdminOrderPlugin.php

---

### Notes
- All files should be cloned/adapted from their storefront (frontend) counterparts, updating namespaces and layout handles for adminhtml.
- JavaScript (browser.js) should collect browser details and store them in session via AJAX to the admin controller.
- Layout XML files should define the admin order page and AJAX blocks for billing, payment, shipping, review, and items.
- Templates should render the same UI as storefront, adapted for admin order context.

### Implemented so far (delta)
- Admin controllers wired: `Controller/Adminhtml/CaptureContextRequest.php`, `Controller/Adminhtml/PaSetup.php`, `Controller/Adminhtml/TransientDataRetrival.php` — adapted to use the admin `Session\Quote` and reuse the gateway `CommandManager` calls used by storefront.
- Admin payment template updated: `view/adminhtml/templates/order/payment.phtml` now injects `captureContextUrl`, `paSetupUrl`, `transientUrl`, and `orderSaveUrl` into `window.cybersourceAdminConfig` and posts browser details to the PA setup endpoint.
- Admin JS modules updated:
	- `view/adminhtml/web/js/browser.js` converted to an AMD module and posts browser details to the `paSetup` admin endpoint.
	- `view/adminhtml/web/js/order.js` converted to an AMD module and initializes the legacy `Cybersource` admin class using the injected config.

### Next steps
- Update admin layout XML to include the `payment.phtml` block into the `sales_order_create` flow (if not already present).
- Enhance JS to drive full Unified Checkout lifecycle (capture context, transient token, PA challenge) using the admin endpoints.
- Update `view/adminhtml/web/js/payment.js` to implement the unified-checkout specific steps (invoking capture context, then transient data retrieval) and integrate with the admin order submission flow.
- Add notes about ACL/DI if needed and run basic integration tests in admin order create UI.

### Completed changes
- Added `view/adminhtml/layout/sales_order_create_index.xml` update to include payment block, JS/CSS assets and UC containers.
- Implemented `view/adminhtml/web/js/payment.js` to perform captureContext → load UC → show → submit transient token to admin endpoint, with PA handling stub.
- Implemented admin `ReturnController` and `WebhookDecisionManagerController`.
- Implemented `Block/Adminhtml/Customer/CardRenderer.php` and completed admin `Order/Save` submission flow.

---

This list ensures all storefront features are available for Admin Order creation in the Magento admin panel.