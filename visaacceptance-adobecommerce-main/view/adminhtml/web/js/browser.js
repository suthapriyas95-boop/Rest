// Admin Order Browser Details JS
define(['jquery'], function ($) {
    'use strict';
    return function (config) {
        var browserDetails = {
            userAgent: navigator.userAgent,
            language: navigator.language,
            platform: navigator.platform
        };

        try {
            if (window.cybersourceAdminConfig && window.cybersourceAdminConfig.paSetupUrl) {
                $.post(window.cybersourceAdminConfig.paSetupUrl, {browser: browserDetails});
            }
        } catch (e) {
            console.log('Cybersource admin browser details error', e);
        }

        return browserDetails;
    };
});
