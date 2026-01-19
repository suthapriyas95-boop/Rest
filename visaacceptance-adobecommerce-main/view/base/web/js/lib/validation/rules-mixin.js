define([
    'jquery'
], function ($) {
    'use strict';

    return function (validator) {

        validator.addRule(
            'validate-name',
            function (value) {
                return !value.match(/[#]/g);
            },
            $.mage.__("Please enter a valid name.")
        );

        return validator;
    };
});