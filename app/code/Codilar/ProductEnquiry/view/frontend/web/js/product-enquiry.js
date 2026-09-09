define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/url',
    'mage/cookies'
], function ($, modal, urlBuilder) {
    'use strict';

    return function () {

        var modalElement = $('#product-enquiry-modal');
        var enquiryForm = $('#product-enquiry-form');
        var enquiryButton = $('#product-enquiry-button');
        var messageBox = $('#product-enquiry-message');

        if (!modalElement.length) {
            console.log('Product enquiry modal not found');
            return;
        }

        /*
         * Initialize modal
         */
        modal({
            type: 'popup',
            responsive: true,
            innerScroll: true,
            modalClass: 'product-enquiry-popup',
            title: 'Product Enquiry',
            buttons: []
        }, modalElement);
        /*
         * Open modal
         */
        enquiryButton.on('click', function () {

            modalElement.modal('openModal');

        });

        /*
         * Submit form
         */
        enquiryForm.on('submit', function (event) {

            event.preventDefault();

            console.log('Product enquiry submit clicked');

            var submitButton = enquiryForm.find(
                'button[type="submit"]'
            );

            submitButton.prop('disabled', true);

            messageBox.empty();

            $.ajax({

                url: urlBuilder.build(
                    'productenquiry/enquiry/submit'
                ),

                type: 'POST',

                dataType: 'json',

                data: {
                    name: $('#enquiry-name').val(),
                    email: $('#enquiry-email').val(),
                    address: $('#enquiry-address').val(),
                    sku: $('#enquiry-sku').val(),
                    quantity: $('#enquiry-quantity').val(),
                    form_key: $.mage.cookies.get('form_key')
                },

                beforeSend: function () {

                    console.log('Sending AJAX request...');

                },

                success: function (response) {

                    console.log('AJAX response:', response);

                    if (response.success) {

                        messageBox.html(
                            '<div class="message success">' +
                            '<div>' +
                            response.message +
                            '</div>' +
                            '</div>'
                        );

                        enquiryForm[0].reset();

                    } else {

                        messageBox.html(
                            '<div class="message error">' +
                            '<div>' +
                            response.message +
                            '</div>' +
                            '</div>'
                        );

                    }

                },

                error: function (xhr) {

                    console.error(
                        'AJAX ERROR:',
                        xhr.status,
                        xhr.responseText
                    );

                    messageBox.html(
                        '<div class="message error">' +
                        '<div>' +
                        'Something went wrong. Please try again.' +
                        '</div>' +
                        '</div>'
                    );

                },

                complete: function () {

                    submitButton.prop('disabled', false);

                }

            });

        });

    };
});
