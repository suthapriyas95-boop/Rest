define([
    'jquery',
    'mage/url',
    'Magento_Ui/js/model/messageList',
    'mage/cookies'
], function ($, urlBuilder, globalMessageList) {
    var setupDone = false;
    'use strict';

    return function (messageContainer, data) {
        var deferred = $.Deferred();
        var messages = messageContainer || globalMessageList;

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
        if (setupDone) {
            deferred.resolve();
            return deferred.promise();
        }
        if (window.capturevalue == true) {
            $.ajax({
                url: urlBuilder.build('cybersourcePayment/frontend/PaSetup', {}),
                data: data,
                method: 'POST'
            }).then(function (setupResponse) {
                if (!setupResponse.success) {
                    messages.addErrorMessage(setupResponse.error_msg);
                    deferred.reject();
                    return;
                }
                var cardinalCollectionForm = document.getElementById('cardinal_collection_form');
                cardinalCollectionForm.setAttribute('action', setupResponse.deviceDataCollectionURL);
                var cardinal_collection_form_input = document.getElementById('cardinal_collection_form_input');
                cardinal_collection_form_input.value = setupResponse.accessToken;
                if (cardinalCollectionForm && setupResponse.deviceDataCollectionURL !== undefined && setupResponse.status) {
                    cardinalCollectionForm.submit();
                    window.addEventListener("message", function (event) {
                        if (event.origin === setupResponse.sandbox || event.origin === setupResponse.production) {
                        }
                    });
                }
                deferred.resolve(setupResponse.token);
                setupDone = true;
            }).fail(function () {
                deferred.reject();
            });
        } else {
            deferred.resolve();
        }

        return deferred.promise();
    };
});