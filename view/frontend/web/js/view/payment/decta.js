define([
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component, rendererList) {
        'use strict';

        rendererList.push(
            {
                type: 'decta_decta',
                component: 'Decta_Decta/js/view/payment/method-renderer/decta'
            }
        );

        /** Add view logic here if needed */
        return Component.extend({});
    });
