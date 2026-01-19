define([
    'jquery',
    'mage/url'
], function ($, urlBuilder) {
    'use strict';

    function UnifiedAdmin(config) {
        this.captureContextUrl = config.captureContextUrl;
        this.transientUrl = config.transientUrl;
        this.payerAuthUrl = config.payerAuthUrl;
        this.methodCode = config.methodCode || 'unifiedcheckout';
        this.init();
    }

    UnifiedAdmin.prototype = {
        init: function () {
            // bind to admin changePaymentMethod event
            var self = this;
            jQuery('#edit_form').on('changePaymentMethod', function (event, method) {
                if (method === self.methodCode) {
                    // intercept submitOrder.cybersource which is already bound
                    // replace it to launch UC before submitting
                    // rely on existing Cybersource.submitAdminOrder flow
                }
            });
        },

        launchUCAndSubmit: function () {
            var self = this;
            // Fetch capture context
            jQuery.post(this.captureContextUrl, {})
                .done(function (response) {
                    var library_url = response.unified_checkout_client_library;
                    var cc = response.captureContext;
                    // load library dynamically
                    require.config({
                        map: { '*': { uc: library_url } }
                    });
                    require(['uc'], function (uc) {
                        uc.Accept(cc).then(function (accept) {
                            return accept.unifiedPayments(false);
                        }).then(function (up) {
                            return up.show({});
                        }).then(function (tt) {
                            // inject transient token into admin form
                            var $form = jQuery('#edit_form');
                            if ($form.length && !jQuery('input[name="transientToken"]').length) {
                                $form.append('<input type="hidden" name="transientToken" value="' + tt + '"/>');
                            } else {
                                jQuery('input[name="transientToken"]').val(tt);
                            }
                            // proceed with existing save flow
                            order._realSubmit();
                        }).catch(function () {
                            alert('Unable to process Unified Checkout.');
                        });
                    });
                })
                .fail(function () {
                    alert('Unable to initialize Unified Checkout.');
                });
        }
    };

    return function (config) {
        return new UnifiedAdmin(config || {});
    };
});
