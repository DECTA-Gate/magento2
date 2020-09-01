define([
      'jquery',
      'Magento_Checkout/js/view/payment/default',
      'mage/url',
      'Magento_Customer/js/customer-data',
      'Magento_Checkout/js/model/error-processor',
      'Magento_Checkout/js/model/full-screen-loader',
    ],
    function ($, Component, url, customerData, errorProcessor, fullScreenLoader) {
        'use strict';

        return Component.extend({
            defaults: {
                redirectAfterPlaceOrder: false,
                template: 'Decta_Decta/payment/decta',
            },

            afterPlaceOrder: function () {
              var redirectUrl = url.build('decta/request/redirect');
              $.post(redirectUrl, 'json')
                .done(function (response) {
                  var responseUrl = response.url;
                  $.mage.redirect(responseUrl);
                })
                .fail(function (response) {
                  errorProcessor.process(response, this.messageContainer);
                })
                .always(function () {
                  fullScreenLoader.stopLoader();
                });
            },

            context: function() {
                return this;
            },

            getCode: function() {
                return 'decta_decta';
            },

            isActive: function() {
                return true;
            }
        });
    }
);