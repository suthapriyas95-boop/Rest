define([
    'jquery',
    'underscore',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/model/full-screen-loader'
  ], function ($, _, additionalValidators, fullScreenLoader) {
    'use strict';

    return {
        setupDone: false,
        initialize: function (code) {
            var that = this;
            require(['CyberSource_Payment/js/view/payment/cardinal'], function (Cardinal) {
                Cardinal.init(that.isTestMode(code));
            });
        },
        placeOrder: function (methodComponent, originalAction, cardNumber, cardType,cardExpMonth, cardExpYear ) {

            var that = this;

            if (typeof methodComponent.validateHandler === 'function' && !methodComponent.validateHandler()) {
                return;
            }

            if (!additionalValidators.validate()) {
                return;
            }

            if (!this.isApplicableForCard(methodComponent.getCode(), cardType)) {
                fullScreenLoader.stopLoader();
                methodComponent.isPlaceOrderActionAllowed(true);
                originalAction();
                return;
            }

            fullScreenLoader.startLoader();
            methodComponent.isPlaceOrderActionAllowed(false);


            require(['CyberSource_Payment/js/view/payment/cardinal'], function (Cardinal) {
                Cardinal.setup(methodComponent.messageContainer, methodComponent.getData())
                    .done(
                        function (sessionId) {
                            that.sessionId = sessionId;
                            fullScreenLoader.stopLoader();
                            methodComponent.isPlaceOrderActionAllowed(true);
                            originalAction();
                            that.setupDone = true;
                        }.bind(methodComponent)
                    )
                    .fail(
                        function () {
                            fullScreenLoader.stopLoader();
                            methodComponent.isPlaceOrderActionAllowed(true);
                        }.bind(methodComponent)
                    );
            });

        },
        visitData: function (methodComponent, data) {
            return $.extend(true, data, {
                additional_data: {
                    sessionId: this.sessionId
                }
            });
        },
        isApplicableForCard: function (methodCode, cardType) {
            var enabledCards = this.getConfig(methodCode, '3ds_cards');

            if (!cardType) {
                return true;
            }

            if (!Array.isArray(enabledCards)) {
                return true;
            }

            return _.indexOf(enabledCards, cardType) !== -1;

        },
        isTestMode: function (methodCode) {
            return Boolean(this.getConfig(methodCode, '3ds_testmode'));
        },
        getConfig: function (methodCode, param) {
            if (typeof window.checkoutConfig.payment[methodCode][param] === 'undefined') {
                return false;
            }
            return window.checkoutConfig.payment[methodCode][param];
        }
        }
  });