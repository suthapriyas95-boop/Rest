define([
    'jquery',
    'CyberSource_Payment/js/action/create-token'
], function ($, getTokenAction) {
    return {
        init: function (debug) {
            if (!debug) {
                return;
            }
        },
        setup: function (messageContainer, data) {
            var setupDeferred = $.Deferred();
            getTokenAction(messageContainer, data).done(function (token) {
                setupDeferred.resolve(token);
            }).fail(function () {
                setupDeferred.reject();
            });

            return setupDeferred.promise();
        },
    };
});
