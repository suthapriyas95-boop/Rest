define([], function () {
        'use strict';

        return {
            isEnabled: function (methodCode) {
                return this.getConfig(methodCode, 'is_cvv_enabled');
            },
            getConfig: function (methodCode, param) {
                if (typeof window.checkoutConfig.payment[methodCode][param] === 'undefined') {
                    return false;
                }
                return Boolean(window.checkoutConfig.payment[methodCode][param]);
            }
    };
});
