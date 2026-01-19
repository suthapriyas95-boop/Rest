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
                            // POST transient token to admin transientDataRetrival endpoint
                            var payload = {
                                transientToken: tt,
                                ScreenHeight: window.screen.height,
                                ScreenWidth: window.screen.width,
                                TimeDifference: new Date().getTimezoneOffset(),
                                ColorDepth: window.screen.colorDepth,
                                JavaEnabled: navigator.javaEnabled(),
                                JavaScriptEnabled: true,
                                Language: navigator.language,
                                AcceptContent: window.navigator.userAgent,
                                vault: false
                            };

                            jQuery.post(self.transientUrl, payload)
                                .done(function (response) {
                                    // if backend returns a redirect or error, handle accordingly
                                    if (response && response.status && response.status == 500) {
                                        alert(response.message || 'Unable to place order.');
                                        return;
                                    }
                                    // proceed with existing save flow after transient processing
                                    order._realSubmit();
                                })
                                .fail(function () {
                                    alert('Unable to process Unified Checkout token on server.');
                                });
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
