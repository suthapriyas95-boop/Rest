/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';

        rendererList.push(
            {
                type: 'unifiedcheckout',
                component: 'CyberSource_Payment/js/view/payment/method-renderer/unified-checkout'
            }
        );

        /** Add view logic here if needed */
        return Component.extend({});
    }
);
