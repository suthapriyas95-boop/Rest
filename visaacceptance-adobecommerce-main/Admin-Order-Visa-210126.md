# Admin Order Visa Parity Checklist (21 Jan 2026)

## ✅ Core Admin Order (Authorize / Authorize+Capture)

**Controllers**
- Controller/Adminhtml/TransientDataRetrival.php
- Controller/Adminhtml/CaptureContextRequest.php

**Layouts**
- view/adminhtml/layout/sales_order_create_index.xml
- view/adminhtml/layout/sales_order_create_load_block_billing_method.xml

**Templates**
- view/adminhtml/templates/cybersource/info.phtml
- view/adminhtml/templates/cybersource/form.phtml

**JS**
- view/adminhtml/web/js/cybersource.js

**Config**
- etc/adminhtml/system.xml
- etc/adminhtml/routes.xml
- etc/acl.xml

**DI (adminhtml)**
- Uses existing admin DI wiring as listed above.

## ✅ Tokenization (Admin)

**Blocks**
- Block/Adminhtml/Order/Create/Billing/Option.php
- Block/Adminhtml/Order/Create/Form.php

**Templates**
- view/adminhtml/templates/cybersource/info.phtml

**DI**
- Uses existing vault wiring in etc/di.xml + etc/frontend/di.xml; no extra admin-specific DI required.

## ✅ 3DS / PA (Admin)

**Controllers**
- Controller/Adminhtml/CaptureContextRequest.php

**JS**
- view/adminhtml/web/js/cybersource.js

**Gateway / Validator**
- Reuses same gateway classes from storefront (no admin duplicates needed).
