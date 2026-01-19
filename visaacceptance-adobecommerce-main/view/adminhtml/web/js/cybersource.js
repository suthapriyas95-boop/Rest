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
        initialize : function (methodCode, controller, orderSaveUrl, nativeAction) {
            var prepare = function (event, method) {
                if (method === 'unifiedcheckout') {
                    this.preparePayment();
                } else {
                    jQuery('#edit_form')
                    .off('submitOrder.cybersource');
                }
            };
            this.iframeId = 'iframeId';
            this.controller = controller;
            this.orderSaveUrl = orderSaveUrl;
            this.nativeAction = nativeAction;
            this.code = methodCode;
            this.inputs = ['cc_type', 'cc_number', 'expiration', 'expiration_yr', 'cc_cid'];
            this.headers = [];
            this.isValid = true;
            this.paymentRequestSent = false;
            this.orderIncrementId = false;
            this.successUrl = false;
            this.hasError = false;
            this.tmpForm = false;

            this.onSubmitAdminOrder = this.submitAdminOrder.bindAsEventListener(this);

            jQuery('#edit_form').on('changePaymentMethod', prepare.bind(this));

            jQuery('#edit_form').trigger(
                'changePaymentMethod',
                [
                jQuery('#edit_form').find(':radio[name="payment[method]"]:checked').val()
                ]
            );
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
