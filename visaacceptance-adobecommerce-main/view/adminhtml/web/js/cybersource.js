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
            this.iframeId = 'iframeId';
            this.controller = controller;
            this.orderSaveUrl = orderSaveUrl;
            this.nativeAction = nativeAction;
            this.captureContextUrl = captureContextUrl;
            this.transientUrl = transientUrl;
            this.tokenFieldSelector = tokenFieldSelector;
            this.code = methodCode;
            if (window.console && console.log) {
                console.log('[UC Admin] init', {
                    code: this.code,
                    captureContextUrl: this.captureContextUrl,
                    transientUrl: this.transientUrl
                });
            }
            this.inputs = ['cc_type', 'cc_number', 'expiration', 'expiration_yr', 'cc_cid'];
            this.headers = [];
            this.isValid = true;
            this.paymentRequestSent = false;
            this.orderIncrementId = false;
            this.successUrl = false;
            this.hasError = false;
            this.tmpForm = false;
            this.ucInitialized = false;
            this.paymentToken = null;
            this.submitAfterToken = false;

            this.onSubmitAdminOrder = this.submitAdminOrder.bindAsEventListener(this);

            jQuery('#edit_form').on('changePaymentMethod', function (event, method) {
                if (method === this.code) {
                    this.showPaymentForm();
                    this.preparePayment();
                } else {
                    jQuery('#edit_form').off('submitOrder.cybersource');
                }
            }.bind(this));

            var attempts = 0;
            var tryInit = function () {
                var current = jQuery('#edit_form').find(':radio[name="payment[method]"]:checked').val();
                if (current === this.code) {
                    if (window.console && console.log) {
                        console.log('[UC Admin] method selected, preparing payment');
                    }
                    this.showPaymentForm();
                    this.preparePayment();
                    return true;
                }
                attempts += 1;
                if (attempts >= 10) {
                    return true;
                }
                return false;
            }.bind(this);

            var timer = window.setInterval(function () {
                if (tryInit()) {
                    window.clearInterval(timer);
                }
            }, 500);
        },

        showPaymentForm : function () {
            jQuery('#payment_form_' + this.code).show();
            if (window.console && console.log) {
                console.log('[UC Admin] show payment form', '#payment_form_' + this.code);
            }
        },

        validate : function () {
            this.isValid = true;
            this.inputs.each(
                function (elemIndex) {
                    if ($(this.code + '_' + elemIndex)) {
                        if (!jQuery.validator.validateElement($(this.code + '_' + elemIndex))) {
                            this.isValid = false;
                        }
                    }
                },
                this
            );

            return this.isValid;
        },

        changeInputOptions : function (param, value) {
            this.inputs.each(
                function (elemIndex) {
                    if ($(this.code + '_' + elemIndex)) {
                        $(this.code + '_' + elemIndex).writeAttribute(param, value);
                    }
                },
                this
            );
        },

        preparePayment : function () {
            this.changeInputOptions('autocomplete', 'off');
            jQuery('#edit_form')
            .off('submitOrder')
            .on('submitOrder.cybersource', this.submitAdminOrder.bind(this));
            this.initUnifiedCheckout();
        },

        initUnifiedCheckout : function () {
            if (this.ucInitialized || !this.captureContextUrl) {
                if (window.console && console.log) {
                    console.log('[UC Admin] initUnifiedCheckout skipped', {
                        ucInitialized: this.ucInitialized,
                        captureContextUrl: this.captureContextUrl
                    });
                }
                return;
            }
            this.ucInitialized = true;

            if (window.console && console.log) {
                console.log('[UC Admin] requesting capture context', this.captureContextUrl);
            }

            jQuery('body').trigger('processStart');
            jQuery.ajax({
                method: 'POST',
                url: this.captureContextUrl,
                data: {}
            }).done(function (response) {
                if (window.console && console.log) {
                    console.log('[UC Admin] capture context response', response);
                }
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
                        if (window.console && console.log) {
                            console.log('[UC Admin] UC library loaded', libraryUrl);
                        }
                        require.config({
                            map: {
                                '*': {
                                    uc: libraryUrl
                                }
                            },
                            onNodeCreated: function (node) {
                                node.setAttribute('integrity', hash);
                                node.setAttribute('crossorigin', 'anonymous');
                            }
                        });

                        require(['uc'], function (uc) {
                            var showArgs;
                            if (response.layoutSelected === 'SIDEBAR') {
                                showArgs = {
                                    containers: {
                                        paymentSelection: '#buttonPaymentListContainer'
                                    }
                                };
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
                        if (window.console && console.log) {
                            console.log('[UC Admin] UC library load failed');
                        }
                        this.showError('Unable to load payment form.');
                    }.bind(this));
            }.bind(this)).fail(function () {
                if (window.console && console.log) {
                    console.log('[UC Admin] capture context request failed');
                }
                this.showError('Unable to load payment form.');
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
            if (!this.transientUrl) {
                self.showError('Unable to process payment token.');
                return;
            }
            if (window.console && console.log) {
                console.log('[UC Admin] submit token');
            }
            jQuery.ajax({
                method: 'POST',
                url: this.transientUrl,
                data: { transientToken: tt }
            }).done(function (response) {
                if (window.console && console.log) {
                    console.log('[UC Admin] token response', response);
                }
                if (response && response.success) {
                    self.setPaymentToken(tt);
                } else {
                    self.showError((response && response.message) ? response.message : 'Unable to process payment token.');
                }
            }).fail(function () {
                if (window.console && console.log) {
                    console.log('[UC Admin] token request failed');
                }
                self.showError('Unable to process payment token.');
            });
        },

        setPaymentToken : function (tt) {
            this.paymentToken = tt;
            var encodedToken;
            try {
                encodedToken = btoa(tt);
            } catch (e) {
                encodedToken = tt;
            }

            if (this.tokenFieldSelector) {
                jQuery(this.tokenFieldSelector).val(encodedToken);
            }

            if (this.submitAfterToken) {
                this.submitAfterToken = false;
                window.setTimeout(function () {
                    this.submitAdminOrder();
                }.bind(this), 0);
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

        showError : function (msg) {
            this.hasError = true;
            if (this.controller == 'onepage') {
                this.resetLoadWaiting();
            }
            alert("Error: " + msg);
        },

        returnQuote : function () {
            var url = this.orderSaveUrl.replace('place', 'returnQuote');
            new Ajax.Request(
                url,
                {
                    onSuccess : function (transport) {
                        try {
                            response = transport.responseText.evalJSON(true);
                        } catch (e) {
                            response = {};
                        }

                        if (response.error_message) {
                            alert("Quote error: " + response.error_message);
                        }

                        this.changeInputOptions('disabled', false);
                        jQuery('body').trigger('processStop');
                        enableElements('save');
                    }.bind(this)
                }
            );
        },

        setLoadWaiting : function () {
            this.headers.each(
                function (header) {
                    header.removeClassName('allow');
                }
            );
            checkout.setLoadWaiting('review');
        },

        resetLoadWaiting : function () {
            this.headers.each(
                function (header) {
                    header.addClassName('allow');
                }
            );
            checkout.setLoadWaiting(false);
        },

        submitAdminOrder : function () {
            // Temporary solution will be removed after refactoring Authorize.Net (sales) functionality
            var editForm = jQuery('#edit_form');
            if (editForm.valid()) {
                // Temporary solution will be removed after refactoring Authorize.Net (sales) functionality
                paymentMethodEl = editForm.find(':radio[name="payment[method]"]:checked');
                this.hasError = false;
                if (paymentMethodEl.val() == this.code) {
                    if (!this.paymentToken && (!this.tokenFieldSelector || !jQuery(this.tokenFieldSelector).val())) {
                        this.submitAfterToken = true;
                        this.initUnifiedCheckout();
                        return;
                    }
                    jQuery('body').trigger('processStart');
                    setLoaderPosition();
                    this.changeInputOptions('disabled', 'disabled');
                    this.paymentRequestSent = true;
                    this.orderRequestSent = true;
                    // Temporary solutions will be removed after refactoring Authorize.Net (sales) functionality
                    editForm.attr('action', this.orderSaveUrl);
                    editForm.append(this.createHiddenElement('controller', this.controller));
                    disableElements('save');
                    // Temporary solutions will be removed after refactoring Authorize.Net (sales) functionality
                    order._realSubmit();
                } else {
                    editForm.attr('action', this.nativeAction);
                    editForm.attr('target', '_top');
                    disableElements('save');
                    // Temporary solutions will be removed after refactoring Authorize.Net (sales) functionality
                    order._realSubmit();
                }
            }
        },

        recollectQuote : function () {
            var area = [ 'sidebar', 'items', 'shipping_method', 'billing_method', 'totals', 'giftmessage' ];
            area = order.prepareArea(area);
            var url = order.loadBaseUrl + 'block/' + area;
            var info = $('order-items_grid').select('input', 'select', 'textarea');
            var data = {};
            for (var i = 0; i < info.length; i++) {
                if (!info[i].disabled && (info[i].type != 'checkbox' || info[i].checked)) {
                    data[info[i].name] = info[i].getValue();
                }
            }

            data.reset_shipping = true;
            data.update_items = true;
            if ($('coupons:code') && $F('coupons:code')) {
                data['order[coupon][code]'] = $F('coupons:code');
            }

            data.json = true;
            new Ajax.Request(
                url,
                {
                    parameters : data,
                    loaderArea : 'html-body',
                    onSuccess : function (transport) {
                        jQuery('#edit_form').submit();
                    }.bind(this)
                }
            );
        },

        saveAdminOrderSuccess : function (data) {
            try {
                response = data.evalJSON(true);
            } catch (e) {
                response = {};
            }

            if (response.redirect) {
                window.location = response.redirect;
            }

            if (response.error_messages) {
                var msg = response.error_messages;
                if (typeof (msg) == 'object') {
                    msg = msg.join("\n");
                }

                if (msg) {
                    alert("Admin error: " + msg);
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
