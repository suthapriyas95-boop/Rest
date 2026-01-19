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
                console.log('[UC Admin] changePaymentMethod fired for', method);
                if (method === self.methodCode) {
                    // Ensure form is visible before proceeding
                    jQuery('#payment_form_' + self.methodCode).show();
                    // intercept submitOrder.cybersource which is already bound
                    // replace it to launch UC before submitting
                    jQuery('#edit_form')
                        .off('submitOrder.cybersource')
                        .on('submitOrder.cybersource', function (e) {
                            e.preventDefault();
                            // If transient already collected, submit immediately
                            if (window.__cybersource_transient_ready) {
                                if (window.order && typeof order._realSubmit === 'function') {
                                    order._realSubmit();
                                } else {
                                    console.error('[UC Admin] order._realSubmit is not available');
                                }
                                return;
                            }
                            // Launch Unified Checkout and submit when ready
                            self.launchUC(true);
                        });

                    // If there are no stored tokens, proactively launch UC so admin
                    // can enter card details before clicking Save.
                    if (jQuery('input[name="payment[token]"]').length === 0) {
                        // don't auto-submit after collecting transient
                        self.launchUC(false);
                    }
                }
            });
            console.log('[UC Admin] initialized, waiting for changePaymentMethod events');

            // If the payment method is already selected on load, trigger UC
            // (admin create page can load with method preselected).
            setTimeout(function () {
                try {
                    var selected = jQuery('#edit_form').find(':radio[name="payment[method]"]:checked').val();
                    if (selected === self.methodCode) {
                        console.log('[UC Admin] method already selected on load, launching UC');
                        // Ensure form is visible
                        jQuery('#payment_form_' + self.methodCode).show();
                        if (jQuery('input[name="payment[token]"]').length === 0 && !window.__cybersource_transient_ready) {
                            self.launchUC(false);
                        }
                    }
                } catch (e) {
                    console.error('[UC Admin] failed to auto-launch on load', e);
                }
            }, 500);
        },

        launchUC: function (submitAfter) {
            var self = this;
            // Ensure form is visible before fetching context
            jQuery('#payment_form_' + this.methodCode).show();
            // Fetch capture context
            console.log('[UC Admin] fetch capture context:', this.captureContextUrl);
            jQuery.post(this.captureContextUrl, {})
                .done(function (response) {
                    console.log('[UC Admin] capture context response received', response);
                    var library_url = response && response.unified_checkout_client_library;
                    var cc = response && response.captureContext;
                    var layoutSelected = response && response.layoutSelected;
                    if (!library_url || !cc) {
                        console.error('[UC Admin] capture context response missing data', response);
                        alert('Unable to initialize Unified Checkout.');
                        return;
                    }
                    // load library dynamically
                    require.config({
                        map: { '*': { uc: library_url } }
                    });
                    require(['uc'], function (uc) {
                        console.log('[UC Admin] UC library loaded from', library_url);
                        if (!uc || !uc.Accept) {
                            console.error('[UC Admin] UC library missing Accept');
                            alert('Unable to load Unified Checkout library.');
                            return;
                        }
                        uc.Accept(cc).then(function (accept) {
                            console.log('[UC Admin] UC Accept resolved');
                            return accept.unifiedPayments(false);
                        }).then(function (up) {
                            console.log('[UC Admin] unifiedPayments initialized');
                            var showArgs;
                            if (layoutSelected === 'SIDEBAR') {
                                showArgs = {
                                    containers: {
                                        paymentSelection: '#buttonPaymentListContainer'
                                    }
                                };
                            } else {
                                showArgs = {
                                    containers: {
                                        paymentSelection: '#buttonPaymentListContainer',
                                        paymentScreen: '#embeddedPaymentContainer'
                                    }
                                };
                            }
                            return up.show(showArgs || {});
                        }).then(function (tt) {
                            console.log('[UC Admin] received transient token from UC');
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
                                    console.log('[UC Admin] transient POST response', response);
                                    // if backend returns a redirect or error, handle accordingly
                                    if (response && response.status && response.status == 500) {
                                        alert(response.message || 'Unable to place order.');
                                        return;
                                    }
                                    // mark transient as ready for later submit
                                    window.__cybersource_transient_ready = true;
                                    if (submitAfter) {
                                        if (window.order && typeof order._realSubmit === 'function') {
                                            order._realSubmit();
                                        } else {
                                            console.error('[UC Admin] order._realSubmit is not available');
                                        }
                                    }
                                })
                                .fail(function () {
                                    console.error('[UC Admin] transient POST failed');
                                    alert('Unable to process Unified Checkout token on server.');
                                });
                        }).catch(function () {
                            console.error('[UC Admin] UC processing failed');
                            alert('Unable to process Unified Checkout.');
                        });
                    });
                })
                .fail(function () {
                    console.error('[UC Admin] capture context fetch failed');
                    alert('Unable to initialize Unified Checkout.');
                });
        },

        launchUCAndSubmit: function () {
            this.launchUC(true);
        }
    };

    return function (config) {
        return new UnifiedAdmin(config || {});
    };
});