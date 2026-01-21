# Admin (Backend) Authorize – Required Files (21-01-2026)

## Controller/Adminhtml/CaptureContextRequest.php
```php
<?php

declare(strict_types=1);

namespace CyberSource\Payment\Controller\Adminhtml;

use CyberSource\Payment\Gateway\PaEnrolledException;
use Magento\Backend\App\Action;
use Magento\Backend\Model\Session\Quote as AdminQuoteSession;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Url\DecoderInterface;
use Magento\Payment\Gateway\Command\CommandManagerInterface;

class CaptureContextRequest extends Action
{
    public const COMMAND_CODE = 'generate_capture_context';
    public const ADMIN_RESOURCE = 'Magento_Sales::sales';

    private $resultJsonFactory;
    private $commandManager;
    private $adminQuoteSession;
    private $logger;
    private $quoteRepository;
    private $config;
    private $urlDecoder;

    public function __construct(
        Action\Context $context,
        CommandManagerInterface $commandManager,
        JsonFactory $resultJsonFactory,
        AdminQuoteSession $adminQuoteSession,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \CyberSource\Payment\Model\LoggerInterface $logger,
        \CyberSource\Payment\Model\Config $config,
        DecoderInterface $urlDecoder
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->commandManager = $commandManager;
        $this->adminQuoteSession = $adminQuoteSession;
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
        $this->config = $config;
        $this->urlDecoder = $urlDecoder;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            if (!$this->getRequest()->isPost()) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Wrong method.'));
            }

            $quote = $this->adminQuoteSession->getQuote();
            if (!$quote || !$quote->getId()) {
                $this->logger->info('Unable to build Capture Context request for admin quote');
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Something went wrong. Please try again.')
                );
            }

            $commandResult = $this->commandManager->executeByCode(
                self::COMMAND_CODE,
                $quote->getPayment()
            )->get();

            $token = $commandResult['response'] ?? null;
            if (is_array($token) && isset($token['rmsg']) && $token['rmsg'] === 'Authentication Failed') {
                $this->logger->info('Unable to load the Unified Checkout form.');
                throw new PaEnrolledException(
                    __('Something went wrong. Please try again.'),
                    401
                );
            }

            $tokenString = (string) $token;
            $payload = explode('.', $tokenString)[1] ?? '';
            $payload = str_replace('_', '/', str_replace('-', '+', $payload));
            $captureContext = json_decode($this->urlDecoder->decode($payload));

            $this->quoteRepository->save($quote);

            $result->setData(
                [
                    'success' => true,
                    'captureContext' => $commandResult['response'],
                    'unified_checkout_client_library' => $captureContext->ctx[0]->data->clientLibrary ?? null,
                    'layoutSelected' => $this->config->getUcLayout(),
                    'setupcall' => $this->config->isPayerAuthEnabled()
                ]
            );
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ['exception' => $e]);
            $this->logger->info('Unable to build Capture Context for admin order.');
            $result->setData(['error_msg' => __('Something went wrong. Please try again.')]);
        }

        return $result;
    }
}
```

## Controller/Adminhtml/TransientDataRetrival.php
```php
<?php

declare(strict_types=1);

namespace CyberSource\Payment\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\Model\Session\Quote as AdminQuoteSession;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Url\DecoderInterface;
use Magento\Payment\Gateway\Command\CommandManagerInterface;

class TransientDataRetrival extends Action
{
    public const COMMAND_CODE = 'get_token_detail';
    public const ADMIN_RESOURCE = 'Magento_Sales::sales';

    private $resultJsonFactory;
    private $commandManager;
    private $adminQuoteSession;
    private $quoteRepository;
    private $logger;
    private $urlDecoder;

    public function __construct(
        Action\Context $context,
        CommandManagerInterface $commandManager,
        JsonFactory $resultJsonFactory,
        AdminQuoteSession $adminQuoteSession,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \CyberSource\Payment\Model\LoggerInterface $logger,
        DecoderInterface $urlDecoder
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->commandManager = $commandManager;
        $this->adminQuoteSession = $adminQuoteSession;
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
        $this->urlDecoder = $urlDecoder;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            if (!$this->getRequest()->isPost()) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Wrong method.'));
            }

            $data = $this->getRequest()->getPostValue('transientToken');
            if (!$data) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Missing token.'));
            }

            $quote = $this->adminQuoteSession->getQuote();
            if (!$quote || !$quote->getId()) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Unable to load admin quote.'));
            }

            $decodedToken = [];
            $tokenParts = explode('.', $data);
            if (isset($tokenParts[1])) {
                $decodedToken = json_decode($this->urlDecoder->decode($tokenParts[1]), true) ?? [];
            }

            $encodedToken = base64_encode($data);
            $quote->getPayment()->setAdditionalInformation('paymentToken', $encodedToken);

            if (isset($decodedToken['content']['processingInformation']['paymentSolution']['value'])) {
                $paymentSolution = $decodedToken['content']['processingInformation']['paymentSolution']['value'] ?? '';
                $quote->getPayment()->setAdditionalInformation('paymentSolution', $paymentSolution);
            }

            $commandResult = $this->commandManager->executeByCode(
                self::COMMAND_CODE,
                $quote->getPayment()
            );

            $response = $commandResult->get();
            $expMonth = $response['paymentInformation']['card']['expirationMonth'] ?? null;
            $expYear = $response['paymentInformation']['card']['expirationYear'] ?? null;
            $maskedPan = $response['paymentInformation']['card']['number'] ?? null;
            $cardType = $response['paymentInformation']['card']['type'] ?? null;

            $quote->getPayment()->setAdditionalInformation('cardType', $cardType);
            $quote->getPayment()->setAdditionalInformation('maskedPan', $maskedPan);
            $quote->getPayment()->setAdditionalInformation('expDate', $expMonth && $expYear ? "$expMonth-$expYear" : null);

            $this->quoteRepository->save($quote);

            $result->setData([
                'success' => true,
                'cardType' => $cardType,
                'maskedPan' => $maskedPan,
                'expDate' => $expMonth && $expYear ? "$expMonth-$expYear" : null
            ]);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ['exception' => $e]);
            $result->setData([
                'status' => 500,
                'message' => $e->getMessage()
            ]);
        }

        return $result;
    }
}
```

## etc/adminhtml/routes.xml
```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:App/etc/routes.xsd">
    <router id="admin">
        <route id="cybersourceadmin" frontName="cybersourceadmin">
            <module name="CyberSource_Payment" />
        </route>
    </router>
</config>
```

## etc/adminhtml/di.xml
```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <type name="CyberSource\Payment\Gateway\Request\Rest\DeviceFingerprintBuilder">
        <arguments>
            <argument name="isAdmin" xsi:type="boolean">true</argument>
        </arguments>
    </type>
    <type name="CyberSource\Payment\Controller\Adminhtml\CaptureContextRequest">
        <arguments>
            <argument name="commandManager" xsi:type="object">CyberSourcePaymentCommandManager</argument>
        </arguments>
    </type>
    <type name="CyberSource\Payment\Controller\Adminhtml\TransientDataRetrival">
        <arguments>
            <argument name="commandManager" xsi:type="object">CyberSourcePaymentCommandManager</argument>
        </arguments>
    </type>
</config>
```

## view/adminhtml/layout/sales_order_create_index.xml
```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceBlock name="order_create_billing_method_form">
            <action method="setMethodFormTemplate">
                <argument name="method" xsi:type="string">unifiedcheckout</argument>
                <argument name="template" xsi:type="string">CyberSource_Payment::cybersource/form.phtml</argument>
            </action>
        </referenceBlock>
    </body>
</page>
```

## view/adminhtml/layout/sales_order_create_load_block_billing_method.xml
```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceBlock name="order_create_billing_method_form">
            <action method="setMethodFormTemplate">
                <argument name="method" xsi:type="string">unifiedcheckout</argument>
                <argument name="template" xsi:type="string">CyberSource_Payment::cybersource/form.phtml</argument>
            </action>
        </referenceBlock>
    </body>
</page>
```

## view/adminhtml/templates/cybersource/form.phtml
```php
<?php
$code = $block->escapeHtml($block->getMethodCode());
$controller = $block->escapeHtml($block->getRequest()->getControllerName());

/** @var CyberSource\Payment\Helper\Data $helper */
$helper = $this->helper('CyberSource\Payment\Helper\Data');
$orderUrl = $block->escapeUrl($helper->getPlaceOrderAdminUrl());
$captureContextUrl = $block->escapeUrl($block->getUrl('cybersourceadmin/order/capturecontextrequest', [
    '_secure' => $block->getRequest()->isSecure()
]));
$transientUrl = $block->escapeUrl($block->getUrl('cybersourceadmin/order/transientdataretrival', [
    '_secure' => $block->getRequest()->isSecure()
]));
?>
<fieldset class="admin__fieldset payment-method" id="payment_form_<?= $escaper->escapeHtmlAttr($code) ?>">
    <div id="buttonPaymentListContainer"></div>
    <div id="embeddedPaymentContainer"></div>
    <input type="hidden" id="cybersource-payment-token" name="payment[additional_data][paymentToken]" value="" />
</fieldset>

<script>
    require([
        'prototype',
        'Magento_Sales/order/create/scripts',
        'Magento_Sales/order/create/form',
        'CyberSource_Payment/js/cybersource'
    ], function () {
        new Cybersource(
            '<?= $escaper->escapeJs($code) ?>',
            '<?= $escaper->escapeJs($controller) ?>',
            '<?= $escaper->escapeJs($orderUrl) ?>',
            '<?= $block->escapeUrl($block->getUrl('*/*/save', ['_secure' => $block->getRequest()->isSecure()])) ?>',
            '<?= $escaper->escapeJs($captureContextUrl) ?>',
            '<?= $escaper->escapeJs($transientUrl) ?>',
            '#cybersource-payment-token'
        );
    });
</script>
```

## view/adminhtml/web/js/cybersource.js
```javascript
(function (factory) {
    if (typeof define === 'function' && define.amd) {
        define(
            [
            "jquery",
            "mage/backend/validation",
            "prototype"
            ],
            factory
        );
    } else {
        factory(jQuery);
    }
}(function (jQuery) {

    window.Cybersource = Class.create();
    Cybersource.prototype = {
        initialize : function (methodCode, controller, orderSaveUrl, nativeAction, captureContextUrl, transientUrl, tokenFieldSelector) {
            this.controller = controller;
            this.orderSaveUrl = orderSaveUrl;
            this.nativeAction = nativeAction;
            this.captureContextUrl = captureContextUrl;
            this.transientUrl = transientUrl;
            this.tokenFieldSelector = tokenFieldSelector;
            this.code = methodCode;

            this.ucInitialized = false;
            this.paymentToken = null;
            this.submitAfterToken = false;

            jQuery('#edit_form').on('changePaymentMethod', function (event, method) {
                if (method === this.code) {
                    this.showPaymentForm();
                    this.preparePayment();
                } else {
                    jQuery('#edit_form').off('submitOrder.cybersource');
                }
            }.bind(this));

            var current = jQuery('#edit_form').find(':radio[name="payment[method]"]:checked').val();
            if (current === this.code) {
                this.showPaymentForm();
                this.preparePayment();
            }
        },

        showPaymentForm : function () {
            jQuery('#payment_form_' + this.code).show();
        },

        preparePayment : function () {
            jQuery('#edit_form')
                .off('submitOrder')
                .on('submitOrder.cybersource', this.submitAdminOrder.bind(this));
            this.initUnifiedCheckout();
        },

        initUnifiedCheckout : function () {
            if (this.ucInitialized || !this.captureContextUrl) {
                return;
            }
            this.ucInitialized = true;

            jQuery('body').trigger('processStart');
            jQuery.ajax({
                method: 'POST',
                url: this.captureContextUrl,
                data: {}
            }).done(function (response) {
                if (!response || response.error_msg) {
                    this.showError(response && response.error_msg ? response.error_msg : 'Unable to load payment form.');
                    return;
                }

                var libraryUrl = response.unified_checkout_client_library;
                var captureContext = response.captureContext;

                if (!libraryUrl || !captureContext) {
                    this.showError('Unable to load payment form.');
                    return;
                }

                fetch(libraryUrl)
                    .then(function (res) { return res.text(); })
                    .then(function (content) { return this.generateSRI(content); }.bind(this))
                    .then(function (hash) {
                        require.config({
                            map: { '*': { uc: libraryUrl } },
                            onNodeCreated: function (node) {
                                node.setAttribute('integrity', hash);
                                node.setAttribute('crossorigin', 'anonymous');
                            }
                        });

                        require(['uc'], function (uc) {
                            var showArgs;
                            if (response.layoutSelected === 'SIDEBAR') {
                                showArgs = { containers: { paymentSelection: '#buttonPaymentListContainer' } };
                                this.handleUnifiedPayments(uc, captureContext, showArgs, true);
                            } else {
                                showArgs = {
                                    containers: {
                                        paymentSelection: '#buttonPaymentListContainer',
                                        paymentScreen: '#embeddedPaymentContainer'
                                    }
                                };
                                this.handleUnifiedPayments(uc, captureContext, showArgs, false);
                            }
                        }.bind(this));
                    }.bind(this))
                    .catch(function () {
                        this.showError('Unable to load payment form.');
                    }.bind(this));
            }.bind(this)).always(function () {
                jQuery('body').trigger('processStop');
            });
        },

        handleUnifiedPayments : function (uc, cc, showArgs, isSidebar) {
            var self = this;
            uc.Accept(cc)
                .then(function (accept) {
                    return accept.unifiedPayments(isSidebar ? true : false);
                })
                .then(function (up) {
                    return up.show(showArgs);
                })
                .then(function (tt) {
                    self.submitToken(tt);
                })
                .catch(function () {
                    self.showError('Unable to process your request. Please try again later.');
                });
        },

        submitToken : function (tt) {
            var self = this;
            jQuery.ajax({
                method: 'POST',
                url: this.transientUrl,
                data: { transientToken: tt }
            }).done(function (response) {
                if (response && response.success) {
                    self.setPaymentToken(tt);
                } else {
                    self.showError((response && response.message) ? response.message : 'Unable to process payment token.');
                }
            }).fail(function () {
                self.showError('Unable to process payment token.');
            });
        },

        setPaymentToken : function (tt) {
            this.paymentToken = tt;
            var encodedToken;
            try { encodedToken = btoa(tt); } catch (e) { encodedToken = tt; }
            if (this.tokenFieldSelector) {
                jQuery(this.tokenFieldSelector).val(encodedToken);
            }
            if (this.submitAfterToken) {
                this.submitAfterToken = false;
                window.setTimeout(function () { this.submitAdminOrder(); }.bind(this), 0);
            }
        },

        generateSRI : async function (content) {
            var encoder = new TextEncoder();
            var data = encoder.encode(content);
            var hashBuffer = await crypto.subtle.digest('SHA-384', data);
            var hashArray = Array.from(new Uint8Array(hashBuffer));
            var hashBase64 = btoa(String.fromCharCode.apply(null, hashArray));
            return 'sha384-' + hashBase64;
        },

        showError : function (msg) { alert("Error: " + msg); },

        submitAdminOrder : function () {
            var editForm = jQuery('#edit_form');
            if (editForm.valid()) {
                var paymentMethodEl = editForm.find(':radio[name="payment[method]"]:checked');
                if (paymentMethodEl.val() == this.code) {
                    if (!this.paymentToken && (!this.tokenFieldSelector || !jQuery(this.tokenFieldSelector).val())) {
                        this.submitAfterToken = true;
                        this.initUnifiedCheckout();
                        return;
                    }
                    editForm.attr('action', this.orderSaveUrl);
                    editForm.append(this.createHiddenElement('controller', this.controller));
                    disableElements('save');
                    order._realSubmit();
                } else {
                    editForm.attr('action', this.nativeAction);
                    editForm.attr('target', '_top');
                    disableElements('save');
                    order._realSubmit();
                }
            }
        },

        createHiddenElement : function (name, value) {
            var field;
            if (isIE) {
                field = document.createElement('input');
                field.setAttribute('type', 'hidden');
                field.setAttribute('name', name);
                field.setAttribute('value', value);
            } else {
                field = document.createElement('input');
                field.type = 'hidden';
                field.name = name;
                field.value = value;
            }
            return field;
        }
    };
}));
```
