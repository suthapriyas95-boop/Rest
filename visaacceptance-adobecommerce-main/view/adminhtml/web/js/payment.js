define([
    'jquery'
], function ($) {
    'use strict';

    return function (config) {
        var captureUrl = config.captureContextUrl;
        var transientUrl = config.transientUrl;
        var paSetupUrl = config.paSetupUrl;
        var container = document.getElementById(config.container || 'cybersource-admin-payment');

        function generateSRI(content) {
            const encoder = new TextEncoder();
            const data = encoder.encode(content);
            return crypto.subtle.digest('SHA-384', data).then(function (hashBuffer) {
                const hashArray = Array.from(new Uint8Array(hashBuffer));
                const hashBase64 = btoa(String.fromCharCode.apply(null, hashArray));
                return 'sha384-' + hashBase64;
            });
        }

        function onError(err) {
            console.error('Cybersource admin payment error', err);
            if (window.alert) {
                alert('Payment failed. See console for details.');
            }
        }

        function init() {
            if (!captureUrl) {
                return;
            }

            // request capture context from admin controller
            $.post(captureUrl, {guestEmail: ''})
                .done(function (response) {
                    try {
                        var library_url = response.unified_checkout_client_library;
                        var cc = response.captureContext;

                        if (!library_url || !cc) {
                            throw new Error('Missing capture context or library URL');
                        }

                        fetch(library_url).then(function (res) { return res.text(); })
                            .then(function (content) { return generateSRI(content); })
                            .then(function (sri) {
                                require.config({
                                    map: { '*': { uc: library_url } },
                                    onNodeCreated: function (node) {
                                        node.setAttribute('integrity', sri);
                                        node.setAttribute('crossorigin', 'anonymous');
                                    }
                                });
                                require(['uc'], function (uc) {
                                    var showArgs = {
                                        containers: {
                                            paymentSelection: '#buttonPaymentListContainer',
                                            paymentScreen: '#embeddedPaymentContainer'
                                        }
                                    };
                                    // show UC and receive transient token
                                    uc.Accept(cc).then(function (accept) { return accept.unifiedPayments(false); })
                                        .then(function (up) { return up.show(showArgs); })
                                        .then(function (tt) {
                                            // tt is transient token
                                            submitTransient(tt);
                                        })
                                        .catch(onError);
                                }, onError);
                            }).catch(onError);
                    } catch (e) {
                        onError(e);
                    }
                }).fail(onError);
        }

        function submitTransient(tt) {
            try {
                var browserDetails = {
                    userAgent: navigator.userAgent,
                    language: navigator.language,
                    platform: navigator.platform
                };

                $.post(transientUrl, {
                    transientToken: tt,
                    vault: false,
                    additional_data: browserDetails
                }).done(function (response) {
                    try {
                        if (response.status && response.status === 500) {
                            onError(response.message || 'Transient processing failed');
                            return;
                        }

                                // If PA setup required response will include sandbox/production and form details
                                if (response.sandbox || response.production || response.accessToken || response.cca || response.stepUpUrl) {
                                    // We need to perform PA/ACS step-up in an iframe for admin.
                                    // Try to obtain the stepUpUrl and accessToken from either transient response or by calling paSetupUrl.
                                    var handlePA = function (paResponse) {
                                        try {
                                            var stepUpUrl = paResponse.stepUpUrl || (paResponse.cca && paResponse.cca.stepUpUrl) || paResponse.parameters && paResponse.parameters.cca && paResponse.parameters.cca.stepUpUrl;
                                            var accessToken = paResponse.accessToken || (paResponse.cca && paResponse.cca.accessToken) || paResponse.parameters && paResponse.parameters.cca && paResponse.parameters.cca.accessToken || paResponse.referenceID || paResponse.referenceId || '';

                                            if (!stepUpUrl) {
                                                console.log('PA setup response missing stepUpUrl, falling back to reload.');
                                                location.reload();
                                                return;
                                            }

                                            // build overlay + iframe + form
                                            var overlay = document.createElement('div');
                                            overlay.id = 'cybersource-stepup-overlay';
                                            overlay.style.position = 'fixed';
                                            overlay.style.left = 0;
                                            overlay.style.top = 0;
                                            overlay.style.width = '100%';
                                            overlay.style.height = '100%';
                                            overlay.style.background = 'rgba(0,0,0,0.6)';
                                            overlay.style.zIndex = 99998;

                                            var iframe = document.createElement('iframe');
                                            iframe.id = 'step-up-iframe';
                                            iframe.name = 'step-up-iframe';
                                            iframe.style.border = '0';
                                            iframe.style.display = 'block';
                                            iframe.style.margin = '40px auto';
                                            iframe.style.width = '500px';
                                            iframe.style.height = '600px';
                                            iframe.style.zIndex = 99999;

                                            var form = document.createElement('form');
                                            form.id = 'step-up-form';
                                            form.method = 'POST';
                                            form.target = iframe.name;
                                            form.style.display = 'none';
                                            form.action = stepUpUrl;

                                            var input = document.createElement('input');
                                            input.type = 'hidden';
                                            input.name = 'accessToken';
                                            input.id = 'step-up-form-input';
                                            input.value = accessToken;
                                            form.appendChild(input);

                                            document.body.appendChild(overlay);
                                            document.body.appendChild(iframe);
                                            document.body.appendChild(form);

                                            // submit into iframe
                                            try {
                                                form.submit();
                                            } catch (e) {
                                                console.warn('Step-up form submit failed', e);
                                            }

                                            // poll returnCheckUrl to detect ACS return
                                            var attempts = 0;
                                            var maxAttempts = 60; // ~2 minutes
                                            var pollInterval = 2000;
                                            var poller = setInterval(function () {
                                                attempts++;
                                                $.get(config.returnCheckUrl).done(function (check) {
                                                    if (check && check.success) {
                                                        clearInterval(poller);
                                                        // cleanup
                                                        try { overlay.parentNode.removeChild(overlay); } catch (e) {}
                                                        try { iframe.parentNode.removeChild(iframe); } catch (e) {}
                                                        try { form.parentNode.removeChild(form); } catch (e) {}

                                                        // After ACS return, refresh payment blocks or recollect quote
                                                        if (window.order && typeof window.order.recollectQuote === 'function') {
                                                            window.order.recollectQuote();
                                                        } else {
                                                            location.reload();
                                                        }
                                                    }
                                                }).fail(function () {
                                                    // ignore transient failures
                                                });

                                                if (attempts >= maxAttempts) {
                                                    clearInterval(poller);
                                                    try { overlay.parentNode.removeChild(overlay); } catch (e) {}
                                                    try { iframe.parentNode.removeChild(iframe); } catch (e) {}
                                                    try { form.parentNode.removeChild(form); } catch (e) {}
                                                    onError('PA challenge timed out');
                                                }
                                            }, pollInterval);

                                        } catch (e) {
                                            onError(e);
                                        }
                                    };

                                    if (response.accessToken || response.stepUpUrl || response.cca) {
                                        handlePA(response);
                                    } else {
                                        // fall back to calling paSetupUrl to get challenge details
                                        $.post(paSetupUrl, {additional_data: browserDetails}).done(function (paResponse) {
                                            handlePA(paResponse);
                                        }).fail(onError);
                                    }

                                    return;
                                }

                        // default: transient token stored on admin quote — refresh payment blocks
                        if (window.order && typeof window.order.recollectQuote === 'function') {
                            window.order.recollectQuote();
                        } else {
                            location.reload();
                        }
                    } catch (e) {
                        onError(e);
                    }
                }).fail(onError);
            } catch (e) {
                onError(e);
            }
        }

        // start
        init();
    };
});
// Admin Order Payment JS
console.log('Admin order payment JS loaded.');
