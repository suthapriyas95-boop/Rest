define([
    'jquery',
    'mage/utils/wrapper',
    'CyberSource_Payment/js/view/payment/payer-authentication',
    'CyberSource_Payment/js/view/payment/pa-enabler'
], function ($, wrapper, pa, Enabler) {
    'use strict';

    return function (Component) {

        if (!Enabler.isEnabled('unifiedcheckout_vault')) {
            return Component;
        }

        return Component.extend({
            initialize: function () {
                this._super();
                pa.initialize(this.getCode());
            },
            placeOrder: function () {
                pa.placeOrder(this, this._super.bind(this));
            },
            getData: function () {
                var data = this._super();

                pa.visitData(this, data);
                return data;
            }
        });

    };
});
