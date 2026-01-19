define([
    'jquery',
    'uiComponent'
], function($, Class) {
    'use strict';

    return Class.extend({
        defaults: {
            $selector: null,
            publicHash: null,
            container: null,
            code: null
        },

        /**
         * @returns {exports.initObservable}
         */
        initObservable: function () {
            this.$selector = $('#edit_form');

            this._super();
            this.initEventHandlers();

            if ($('[name="payment[method]"][value="' + this.code + '"]').prop('checked')) {
                $('#payment_form_' + this.code).find('input:radio:first').trigger('click');
            }

            return this;
        },

        initEventHandlers: function () {
            $('#' + this.container).find('[name="payment[token_switcher]"]')
                .on('click', this.setPaymentDetails.bind(this));
        },

        setPaymentDetails: function (event) {
            $(event.currentTarget).prop('checked', true);
            this.$selector.find('[name="payment[public_hash]"]').val(this.publicHash);
        }
    });
});
