/* globals InvoicedBillingPortal */
(function () {
    'use strict';

    let cardComponent,
        adyenData = null,
        adyenCapturedData = {
            state: null,
            country: null,
            email: null,
            phone: null,
            address1: null,
            address2: null,
            city: null,
            postal_code: null,
            first_name: null,
            last_name: null,
        };
    let stateIsValid = false;

    const paymentMethod = {
        init: init,
        capture: capture,
    };

    InvoicedBillingPortal.payments.register('affirm', paymentMethod);

    function init() {
        adyenData = InvoicedBillingPortal.util.getJsonValue('affirm-card-data');
        loadAdyenDropIn();
    }

    async function loadAdyenDropIn() {
        const configuration = {
            environment: adyenData.environment,
            amount: adyenData.transactionData.amount,
            locale: adyenData.locale,
            countryCode: adyenData.countryCode,
            clientKey: adyenData.clientKey,
            onEnterKeyPressed: function () {
                // Do not allow the form to be submitted on enter key press into the
                // drop-in element because it bypasses the payment form submit handler.
                // If we want this behavior in the future then we have to set up a callback
                // to delegate it to the parent form's submit handler.
            },
            onSubmit: async (state, component, actions) => {
                try {
                    // Make a POST /payments request from the backend
                    const result = await makePaymentsCall(state.data, adyenData.transactionData);

                    // If the /payments request fails, or if an unexpected error occurs.
                    if (!result.resultCode) {
                        fail();

                        return;
                    }

                    if (result.resultCode === 'Refused') {
                        fail('Payment Refused.');

                        return;
                    }

                    const { resultCode, action, order, donationToken } = result;
                    // If the /payments request is successful, resolve whichever of the listed objects are available.
                    actions.resolve({
                        resultCode,
                        action,
                        order,
                        donationToken,
                    });
                } catch (error) {
                    fail();
                }
            },
            onChange: state => {
                const currentMethod = $('#paymentMethodSelector').val();
                $('.payment-button').prop('disabled', currentMethod === 'affirm' && !state.isValid);
                stateIsValid = state.isValid;
            }
        };

        const dropInConfiguration = {
            paymentMethodsConfiguration: {
                affirm: {},
            },
        };

        const { AdyenCheckout, Affirm } = window.AdyenWeb;
        const checkout = await AdyenCheckout(configuration);
        cardComponent = new Affirm(checkout, dropInConfiguration).mount('#affirm-card-container');

        $('.adyen-checkout__button.adyen-checkout__button--pay').hide();
    }

    function capture(formParameters, onSuccess, onFailure) {
        InvoicedBillingPortal.util.hideErrors();
        $.ajax({
            method: 'GET',
            url: '/api/flows/' + adyenData.transactionData.reference + '/payable',
            headers: {
                Accept: 'application/json',
            },
        }).then(function () {
            doCapture(formParameters, onSuccess, onFailure);
        }).fail(function (response) {
            $('.invoice-payment-errors').html(response.responseJSON.message).show();
            onFailure(true);
        });
    }

    function doCapture(formParameters, onSuccess, onFailure) {
        // Do not proceed if the drop-in form is not valid
        if (!stateIsValid) {
            onFailure();
            return;
        }

        adyenCapturedData.state = $('#affirm-card-container .adyen-checkout__field--stateOrProvince .adyen-checkout__filter-input').val();
        adyenCapturedData.country = $('#affirm-card-container .adyen-checkout__field--country .adyen-checkout__filter-input').val();
        adyenCapturedData.email = $('#affirm-card-container [name=shopperEmail]').val();
        adyenCapturedData.phone = $('#affirm-card-container [name=telephoneNumber]').val();
        adyenCapturedData.address1 = $('#affirm-card-container [name=street]').val();
        adyenCapturedData.address2 = $('#affirm-card-container [name=houseNumberOrName]').val();
        adyenCapturedData.city = $('#affirm-card-container [name=city]').val();
        adyenCapturedData.postal_code = $('#affirm-card-container [name=postalCode]').val();
        adyenCapturedData.first_name = $('#affirm-card-container [name=firstName]').val();
        adyenCapturedData.last_name = $('#affirm-card-container [name=lastName]').val();

        cardComponent.submit();
    }

    async function makePaymentsCall(data, transactionData) {
        Object.assign(data, transactionData);

        return $.ajax({
            method: 'POST',
            url: '/api/adyen/affirm',
            headers: {
                Accept: 'application/json',
            },
            data: data,
        });
    }

    async function fail(error) {
        if (!error) {
            error = 'Could not validate your payment information. Please check below to make sure you have entered in your payment information correctly.';
        }
        InvoicedBillingPortal.util.showError(
            error,
            'invoiced-affirm-errors',
            true
        );

        $('#affirm-card-container').remove();
        $('#affirm-card-data').before('<div class="mb-3" id="affirm-card-container"></div>');

        await loadAdyenDropIn();
    }
})();
