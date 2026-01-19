/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See LICENSE.txt for license details.
 */
/*browser:true*/
/*global define*/
define([
    'jquery',
    'CyberSource_Payment/js/view/payment/payer-authentication',
    'Magento_Vault/js/view/payment/method-renderer/vault',
    'mage/url',
    'Magento_Checkout/js/action/select-payment-method',
], function ($,pa, VaultComponent,urlBuilder ) {
    'use strict';

    return VaultComponent.extend({
        defaults: {
            template: 'CyberSource_Payment/payment/vault-form',
            additionalData: {}
        },

        /**
         * Get data
         *
         * @returns {Object}
         */
        getData: function () {
            var data = {
                'method': this.getCode(),
                'additional_data': {
                    'public_hash': this.publicHash
                }
            };

            data['additional_data'] = _.extend(data['additional_data'], this.additionalData);
            data['additional_data']['cvv'] = $(this.getSelector('cc_cid')).val();

            return data;
        },
        /**
         * Get last 4 digits of card
         * @returns {String}
         */
        getMaskedCard: function () {
            return this.details.maskedCC;
        },

        /**
         * Get expiration date
         * @returns {String}
         */
        getExpirationDate: function () {
            return this.details.expirationDate;
        },

        /**
         * Get card type
         * @returns {String}
         */
        getCardType: function () {
            return this.details.type;
        },

        getTitle: function () {
            return this.details.title;
        },

        getSelector: function (field) {
            return '#' + this.getId() + '_' + field;
        },

        validate: function () {
            var $form = $(this.getSelector('form'));
            return $form.validation() && $form.validation('isValid');
        },

        getIsCvvEnabled: function () {
            return window.checkoutConfig.payment[this.getCode()].is_cvv_enabled;
        },
        placeOrder: function () {
            pa.placeOrder(this, this._super.bind(this));
        },
    });
});