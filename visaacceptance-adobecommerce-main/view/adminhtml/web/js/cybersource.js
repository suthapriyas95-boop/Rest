define([
    'jquery',
    'mage/backend/validation'
], function ($) {
    'use strict';

    function Cybersource(methodCode, controller, orderSaveUrl, nativeAction) {
        var prepare = function (event, method) {
            if (method === 'unifiedcheckout') {
                this.preparePayment();
            } else {
                $('#edit_form').off('submitOrder.cybersource');
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

        this.onSubmitAdminOrder = this.submitAdminOrder.bind(this);

        $('#edit_form').on('changePaymentMethod', prepare.bind(this));

        $('#edit_form').trigger('changePaymentMethod', [$('#edit_form').find(':radio[name="payment[method]"]:checked').val()]);
    }

    Cybersource.prototype = {
        validate: function () {
            this.isValid = true;
            this.inputs.forEach(function (elemIndex) {
                var el = $('#' + this.code + '_' + elemIndex);
                if (el.length) {
                    if (typeof jQuery.validator !== 'undefined') {
                        try {
                            if (!jQuery.validator.validateElement(el[0])) {
                                this.isValid = false;
                            }
                        } catch (e) {
                            if (!el.val()) {
                                this.isValid = false;
                            }
                        }
                    } else if (!el.val()) {
                        this.isValid = false;
                    }
                }
            }.bind(this));

            return this.isValid;
        },

        changeInputOptions: function (param, value) {
            this.inputs.forEach(function (elemIndex) {
                var el = $('#' + this.code + '_' + elemIndex);
                if (el.length) {
                    el.attr(param, value);
                }
            }.bind(this));
        },

        preparePayment: function () {
            this.changeInputOptions('autocomplete', 'off');
            $('#edit_form').off('submitOrder').on('submitOrder.cybersource', this.submitAdminOrder.bind(this));
        },

        showError: function (msg) {
            this.hasError = true;
            if (this.controller == 'onepage') {
                this.resetLoadWaiting();
            }
            alert('Error: ' + msg);
        },

        returnQuote: function () {
            var url = this.orderSaveUrl.replace('place', 'returnQuote');
            $.ajax({
                url: url,
                method: 'GET',
                success: function (transport) {
                    var response;
                    try {
                        response = (typeof transport === 'string') ? JSON.parse(transport) : transport;
                    } catch (e) {
                        response = {};
                    }

                    if (response.error_message) {
                        alert('Quote error: ' + response.error_message);
                    }

                    this.changeInputOptions('disabled', false);
                    $('body').trigger('processStop');
                    if (typeof enableElements === 'function') {
                        enableElements('save');
                    }
                }.bind(this)
            });
        },

        setLoadWaiting: function () {
            this.headers.forEach(function (header) {
                $(header).removeClass('allow');
            });
            if (typeof checkout !== 'undefined' && typeof checkout.setLoadWaiting === 'function') {
                checkout.setLoadWaiting('review');
            }
        },

        resetLoadWaiting: function () {
            this.headers.forEach(function (header) {
                $(header).addClass('allow');
            });
            if (typeof checkout !== 'undefined' && typeof checkout.setLoadWaiting === 'function') {
                checkout.setLoadWaiting(false);
            }
        },

        submitAdminOrder: function () {
            var editForm = $('#edit_form');
            if (editForm.valid && editForm.valid()) {
                var paymentMethodEl = editForm.find(':radio[name="payment[method]"]:checked');
                this.hasError = false;
                if (paymentMethodEl.val() == this.code) {
                    $('body').trigger('processStart');
                    if (typeof setLoaderPosition === 'function') {
                        setLoaderPosition();
                    }
                    this.changeInputOptions('disabled', 'disabled');
                    this.paymentRequestSent = true;
                    this.orderRequestSent = true;
                    editForm.attr('action', this.orderSaveUrl);
                    editForm.append(this.createHiddenElement('controller', this.controller));
                    if (typeof disableElements === 'function') {
                        disableElements('save');
                    }
                    if (typeof order !== 'undefined' && typeof order._realSubmit === 'function') {
                        order._realSubmit();
                    }
                } else {
                    editForm.attr('action', this.nativeAction);
                    editForm.attr('target', '_top');
                    if (typeof disableElements === 'function') {
                        disableElements('save');
                    }
                    if (typeof order !== 'undefined' && typeof order._realSubmit === 'function') {
                        order._realSubmit();
                    }
                }
            }
        },

        recollectQuote: function () {
            var area = ['sidebar', 'items', 'shipping_method', 'billing_method', 'totals', 'giftmessage'];
            area = order.prepareArea(area);
            var url = order.loadBaseUrl + 'block/' + area;
            var info = $('#order-items_grid').find('input, select, textarea');
            var data = {};
            info.each(function () {
                var el = $(this);
                if (!el.prop('disabled') && (this.type !== 'checkbox' || el.prop('checked'))) {
                    data[el.attr('name')] = el.val();
                }
            });

            data.reset_shipping = true;
            data.update_items = true;
            if ($('#coupons\\:code').length && $('#coupons\\:code').val()) {
                data['order[coupon][code]'] = $('#coupons\\:code').val();
            }

            data.json = true;
            $.ajax({
                url: url,
                method: 'GET',
                data: data,
                success: function (transport) {
                    $('#edit_form').submit();
                }.bind(this)
            });
        },

        saveAdminOrderSuccess: function (data) {
            var response;
            try {
                response = (typeof data === 'string') ? JSON.parse(data) : data;
            } catch (e) {
                response = {};
            }

            if (response.redirect) {
                window.location = response.redirect;
            }

            if (response.error_messages) {
                var msg = response.error_messages;
                if (typeof (msg) == 'object') {
                    msg = msg.join('\n');
                }

                if (msg) {
                    alert('Admin error: ' + msg);
                }
            }
        },

        createHiddenElement: function (name, value) {
            var field = document.createElement('input');
            field.type = 'hidden';
            field.name = name;
            field.value = value;
            return field;
        }
    };

    // Export constructor
    window.Cybersource = Cybersource;
    return Cybersource;
});
