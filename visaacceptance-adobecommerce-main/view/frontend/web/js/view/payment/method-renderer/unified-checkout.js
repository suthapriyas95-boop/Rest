define([
    'jquery',
    'CyberSource_Payment/js/model/error-processor-mixin',
    'ko',
    'Magento_Ui/js/model/messageList',
    'Magento_Vault/js/view/payment/vault-enabler',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/action/set-billing-address',
    'Magento_Checkout/js/action/set-shipping-information',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/url',
    'jquery/ui',
    'mage/translate',
],    
    function (
        $,
        errorProcess,
        ko,
        messageContainer,
        VaultEnabler,
        Component,
        additionalValidators,
        quote,
        customerData,
        setBillingAddress,
        setShippingInformation,
        fullScreenLoader,
        urlBuilder
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'CyberSource_Payment/payment/payment-form',
                active: ko.observable(false),
                code: 'unifiedcheckout',
                saveCardForLater: ko.observable(false),
                grandTotalAmount: null,
                currencyCode: null,
                initErrorMessage: ko.observable(null),
                count: 0,
                imports: {
                    onActiveChange: 'active'
                }
            },
            /**
             * @returns {exports.initialize}
             */
            initialize: function () {
                this._super();
                this.vaultEnabler = new VaultEnabler();
                this.vaultEnabler.setPaymentCode(this.getVaultCode());
                return this;
            },

            initObservable: function () {
                var self = this;
                this._super().observe(['active']);
                this.initToken();
                return this;
            },

            onActiveChange: function (isActive) {
                if (isActive && !this.initialized()) {
                    this.initToken();
                }
                return;
            },

            getCode: function () {
                return this.code;
            },

            isVaultEnabled: function () {
                return this.vaultEnabler.isVaultEnabled();
            },

            getVaultCode: function () {
                return window.checkoutConfig.payment[this.getCode()].vaultCode;
            },

            isActive: function () {
                var active = this.getCode() === this.isChecked();
                this.active(active);
                return active;
            },

            submit: function (tt) {
                fullScreenLoader.startLoader();
                var $deferred = $.Deferred();
                var active = this.getCode() === this.isChecked();
                var saveCardForLater = this.isVaultEnabled() && this.vaultEnabler.isActivePaymentTokenEnabler();
                $.ajax({
                    method: 'POST',
                    url: urlBuilder.build('cybersourcePayment/frontend/TransientDataRetrival'),
                    data: {
                        'ScreenHeight': window.screen.height,
                        'ScreenWidth': window.screen.width,
                        'TimeDifference': new Date().getTimezoneOffset(),
                        'ColorDepth': window.screen.colorDepth,
                        'JavaEnabled': navigator.javaEnabled(),
                        'JavaScriptEnabled': typeof window !== 'undefined' && typeof window.document !== 'undefined' && typeof window.document.createElement === 'function',
                        'Language': navigator.language,
                        'AcceptContent': window.navigator.userAgent,
                        transientToken: tt,
                        vault: saveCardForLater,
                    },
                    success: function (response) {
                        if (response.status == 500) {
                            messageContainer.addErrorMessage({
                                message: response.message
                            })
                            window.location.replace(urlBuilder.build(response.redirect_url))
                        }

                        else if (response.status == 200) {
                            window.location.replace(urlBuilder.build(response.redirect_url))
                        }
                        if (response.deviceDataCollectionURL != null) {
                            var cardinalCollectionForm = document.getElementById('cardinal_collection_form');
                            cardinalCollectionForm.setAttribute('action', response.deviceDataCollectionURL);
                            var cardinal_collection_form_input = document.getElementById('cardinal_collection_form_input')
                            cardinal_collection_form_input.value = response.accessToken;
                            if (cardinalCollectionForm && response.deviceDataCollectionURL !== undefined && response.status) {
                                cardinalCollectionForm.submit();
                                window.addEventListener("message", function (event) {
                                    if (event.origin === response.sandbox || event.origin === response.production) {
                                    }
                                });
                            }

                            var additionalAttributes = {};

                            // Place the order
                            placeOrder(additionalAttributes);
                            function placeOrder(additionalAttributes) 
                            {
                                require(['Magento_Checkout/js/action/place-order', 'Magento_Checkout/js/model/quote', 'Magento_Checkout/js/action/redirect-on-success'],
                                    function (placeOrderAction, quote, redirectOnSuccessAction) {
                                        let attributes = { 'method': 'unifiedcheckout' }; // Update with your payment method code
                                        if (additionalAttributes) {
                                            attributes = { ...attributes, ...additionalAttributes };
                                        }
                                        placeOrderAction(attributes).done(
                                            function () {
                                                redirectOnSuccessAction.execute();
                                                // Handle success if needed
                                            }
                                        ).fail(
                                            function (response) {
                                                if (response.responseJSON.code !== "PENDING_AUTHENTICATION") {
                                                    window.setTimeout(function() {
                                                        window.location.href = urlBuilder.build('/checkout#payment');
                                                        window.location.reload();
                                                        fullScreenLoader.stopLoader();
                                                    }, 32000);
                                                }
                                        
                                                else if (response.responseJSON.code === "PENDING_AUTHENTICATION" && active === true) {
                                                    window.setTimeout(function() {
                                                        window.location.reload();
                                                        fullScreenLoader.stopLoader();
                                                    }, 45000);
                                                }
                                                
                                            }
                                        );
                                    }
                                );
                            }

                        }
                    }
                });
            },
            
            handleLoader: function (action) {
                if (action === 'start') {
                    fullScreenLoader.startLoader();
                } else if (action === 'stop') {
                    fullScreenLoader.stopLoader();
                }
            },
            
            handleUnifiedPayments: function (uc, cc, showArgs, isSidebar) {
                var self = this;
                uc.Accept(cc)
                    .then(function (accept) {
                        return accept.unifiedPayments(isSidebar ? true : false);
                    })
                    .then(function (up) {
                        return up.show(showArgs);
                    })
                    .then(function (tt) {
                        self.submit(tt);
                    })
                    .catch(function () {
                        alert('Unable to process your request. Please try again later.');
                        location.reload();
                    });
            },

            initToken: function () {
                var self = this;
                fullScreenLoader.startLoader();
                var sessionData = {
                    'guestEmail': quote.guestEmail
                };

                $.ajax(
                    urlBuilder.build('cybersourcePayment/frontend/CaptureContextRequest', {}), {
                    'data': sessionData,
                    'method': 'POST'
                }
                ).then(
                    function (response) {                        
                        var library_url = response.unified_checkout_client_library; 
                        var cc = response.captureContext;
                        window.capturevalue=response.setupcall;                        
                        // Fetch script content and generate SRI hash
                        fetch(library_url)
                            .then(res => res.text())
                            .then(content => generateSRI(content))
                            .then(hash => {
                                // Dynamically load UC library
                                require.config({                            
                                        map: {
                                            '*': {
                                                uc: library_url
                                            }
                                        },
                                    //Configure RequireJS dynamically with integrity & crossorigin
                                    onNodeCreated: function (node, config, moduleName, url) {                            
                                        node.setAttribute('integrity', hash);
                                        node.setAttribute('crossorigin', 'anonymous');
                                    }
                                });
                                //Load UC library via RequireJS
                                require(['uc'], function (uc) {
                                    var showArgs;
                                    if (response.layoutSelected === "SIDEBAR") {
                                        showArgs = {
                                            containers: {
                                                paymentSelection: "#buttonPaymentListContainer"
                                            }
                                        };
                                    self.handleUnifiedPayments(uc, cc, showArgs, true);
                                    } else {
                                        showArgs = {
                                            containers: {
                                                paymentSelection: "#buttonPaymentListContainer",
                                                paymentScreen: "#embeddedPaymentContainer"
                                            }
                                        };
                                        self.handleUnifiedPayments(uc, cc, showArgs, false);
                                    }
                                });
                        });
                    }).always(function () {
                        self.handleLoader('stop');
                    });
                    //Generate SRI hash using Web Crypto API
                    async function generateSRI(content) {
                        const encoder = new TextEncoder();
                        const data = encoder.encode(content);
                        const hashBuffer = await crypto.subtle.digest('SHA-384', data);
                        const hashArray = Array.from(new Uint8Array(hashBuffer));
                        const hashBase64 = btoa(String.fromCharCode(...hashArray));
                        return `sha384-${hashBase64}`;
                    }
            } 
        });
    });