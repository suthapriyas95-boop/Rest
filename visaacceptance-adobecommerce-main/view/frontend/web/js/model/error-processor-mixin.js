define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/url',
    'Magento_Ui/js/model/messageList',
    'mage/cookies'
], function ($, wrapper, quote, fullScreenLoader,urlBuilder, globalMessageList) {
    'use strict';

    return function (errorProcessor,data) {
        var deferred = $.Deferred();
     
        data = $.extend(data || {}, {
            'form_key': $.mage.cookies.get('form_key'),
            'ScreenHeight': window.screen.height,
            'ScreenWidth': window.screen.width,
            'TimeDifference': new Date().getTimezoneOffset(),
            'ColorDepth': window.screen.colorDepth,
            'JavaEnabled': navigator.javaEnabled(),
            'JavaScriptEnabled': typeof window !== 'undefined' && typeof window.document !== 'undefined' && typeof window.document.createElement === 'function',
            'Language': navigator.language,
            'AcceptContent': window.navigator.userAgent,
        });

        function getMethodCode(quote)
        {
            var code = (quote.paymentMethod() && quote.paymentMethod().method)
                ? quote.paymentMethod().method
                : 'chcybersource';

            return code.replace(/_(\d+)$/, '');
        }
        function placeOrder(additionalAttributes, messageContainer)
        {
            require(
                ['Magento_Checkout/js/action/place-order', 'Magento_Checkout/js/model/quote', 'Magento_Checkout/js/action/redirect-on-success'],
                function (placeOrderAction, quote, redirectOnSuccessAction) {
                    let attributes = { 'method': getMethodCode(quote) };
                    if (additionalAttributes) {
                        attributes = { ...attributes, ...additionalAttributes };
                    }
                    placeOrderAction(attributes, messageContainer).done(
                        function () {
                            redirectOnSuccessAction.execute();
                        }
                    ).fail(
                        function () {
                            fullScreenLoader.stopLoader();
                        }
                    );
                }
            );
        }

        errorProcessor.process = wrapper.wrap(
            errorProcessor.process,
            function (originalProcess, response, messageContainer) {
                if (response.responseJSON.code == "CUSTOMER_AUTHENTICATION_REQUIRED") {
                    window.setTimeout(function () {
                        window.location.reload();
                        fullScreenLoader.stopLoader();
                    }, 45000);
                }
                if (response.responseJSON.code == "INVALID_ACCOUNT" || response.responseJSON.code == "PROCESSOR_DECLINED" || response.responseJSON.code == "PROCESSOR_ERROR"){
                    window.setTimeout(function () {
                        window.location.reload();
                        fullScreenLoader.stopLoader();
                    }, 500);
                }

                if (response.responseJSON.code === "CONSUMER_AUTHENTICATION_FAILED") {
                    window.setTimeout(function () {
                        window.location.reload();
                        fullScreenLoader.stopLoader();
                    }, 200);
                }
                if (response.responseJSON && response.responseJSON.code) {
                    if (response.responseJSON.code == 'PENDING_AUTHENTICATION') {
                        var responseObj = JSON.parse(response.responseText);
                        const windowsize = responseObj.parameters.cca.Payload;
                        var formIframe = document.getElementById('step-up-iframe');
                        try {
                            const decodedString = atob(windowsize);
                            const jsonObject = JSON.parse(decodedString);
                            const challengeWindowSize = jsonObject.challengeWindowSize;

                            formIframe.width =
                                challengeWindowSize === '01' ? '250px' :
                                challengeWindowSize === '02' ? '390px' :
                                challengeWindowSize === '03' ? '500px' :
                                challengeWindowSize === '04' ? '600px' :
                                '100%';

                            formIframe.height =
                                challengeWindowSize === '01' ? '400px' :
                                challengeWindowSize === '02' ? '400px' :
                                challengeWindowSize === '03' ? '600px' :
                                challengeWindowSize === '04' ? '400px' :
                                '100%';
                        } catch (error) {
                            formIframe.width = '400px';
                            formIframe.height = '400px';
                        }
                        var stepUpForm = document.getElementById('step-up-form');
                        stepUpForm.setAttribute('action', responseObj.parameters.cca.stepUpUrl);
                        var stepup_form_input = document.getElementById('step-up-form-input');
                        stepup_form_input.value = responseObj.parameters.cca.accessToken;
                        var stepUpIframe = document.querySelector('#step-up-iframe');
                        stepUpIframe.style.display = "block";
                        var overlay = document.getElementById('overlay');
                        overlay.style.display = "block";
                        if (stepUpForm) {
                             stepUpForm.submit();
                             var checkTransactionId = setInterval(function () {
                                var element = document.querySelector('#step-up-iframe').contentWindow.length;
                                if (element != '1') {
                                    clearInterval(checkTransactionId);
                                    stepUpIframe.style.display = "none";
                                    overlay.style.display = "none";
                                    placeOrder({
                                        'extension_attributes': { 'cca_response': responseObj.parameters.cca.accessToken }
                                      }, messageContainer);
                                }
                             }, 2000);
                        }
                    } else if (response.responseJSON.code == 'DECLINED') {
                        placeOrder({
                            'extension_attributes': {'cca_response': ''}
                        })
                    } else if (response.responseJSON.code === 'CUSTOMER_AUTHENTICATION_REQUIRED') {
                        placeOrder({
                            'extension_attributes': {'cca_response': ''}
                        })
                    } else if (response.status == 500) {
                        return originalProcess(response, messageContainer);
                    } else {
                        return originalProcess(response, messageContainer);
                    }
                } else {
                    return originalProcess(response, messageContainer);
                }
            }
        );

        return errorProcessor;
    };
});