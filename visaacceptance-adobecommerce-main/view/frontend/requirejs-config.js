var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/model/error-processor': {
                'CyberSource_Payment/js/model/error-processor-mixin': true
            },
            'CyberSource_Payment/js/view/payment/method-renderer/vault': {
                'CyberSource_Payment/js/view/payment/vault-mixin': true
            }
        }
    }
};
