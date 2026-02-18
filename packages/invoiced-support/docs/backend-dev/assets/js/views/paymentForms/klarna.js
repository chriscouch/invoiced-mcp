/* globals InvoicedBillingPortal */
(function () {
    'use strict';

    let cardComponent,
        adyenData = null,
        configuration = null;
    let stateIsValid = false;

    const paymentMethod = {
        init: init,
        capture: capture,
    };

    InvoicedBillingPortal.payments.register('klarna', paymentMethod);

    function init() {
        adyenData = InvoicedBillingPortal.util.getJsonValue('klarna-card-data');
        configuration = {
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
                    const emailElement = $('#klarna-email');
                    if (!emailElement.val()) {
                        fail('Please enter your email address.');
                        return;
                    }

                    const shopperName = $('.payment-method-form.klarna [name=shopperName]').val();
                    const street = $('.payment-method-form.klarna [name=street]').val();
                    const houseNumberOrName = $('.payment-method-form.klarna [name=houseNumberOrName]').val();
                    const city = $('.payment-method-form.klarna [name=city]').val();
                    const stateOrProvince = $('.payment-method-form.klarna [name=stateOrProvince]').val();
                    const postalCode = $('.payment-method-form.klarna [name=postalCode]').val();
                    const country = $('.payment-method-form.klarna [name=country]').val();

                    if (!shopperName || !street || !city || !stateOrProvince || !postalCode || !country) {
                        fail('Please fill in your billing address.');
                        return;
                    }

                    const inputData = {
                        shopperEmail: emailElement.val(),
                        shopperName: shopperName,
                        billingAddress: {
                            street: street,
                            houseNumberOrName: houseNumberOrName,
                            city: city,
                            stateOrProvince: stateOrProvince,
                            postalCode: postalCode,
                            country: country,
                        },
                    };

                    // Make a POST /payments request from the backend
                    const result = await makePaymentsCall(state.data, adyenData.transactionData, inputData);

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
        };
        loadAdyenDropIn();
    }

    $('.payment-method-form.klarna [required], #paymentMethodSelector').change(() => {
        const currentMethod = $('#paymentMethodSelector').val();
        const disabled = currentMethod === 'klarna' && $('.payment-method-form.klarna [required]:invalid').length;
        $('.payment-button').prop('disabled', disabled);

        stateIsValid = !disabled;
    });

    $('[name="klarna_type"]').change(() => {
        refreshCardContainer();
    });

    async function loadAdyenDropIn() {
        $('.vaulting-hidden-field,.autopay-hidden-field').hide();

        const type = $('[name="klarna_type"]:checked').val();

        if (!type) {
            return;
        }

        const { AdyenCheckout, Klarna } = window.AdyenWeb;
        const checkout = await AdyenCheckout(configuration);
        cardComponent = new Klarna(checkout, {
            useKlarnaWidget: false,
            type: type,
        }).mount('#klarna-card-container');


        $('.adyen-checkout__button--standalone.adyen-checkout__button--pay').hide();
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

        cardComponent.submit();
    }

    async function makePaymentsCall(data, transactionData, inputData) {
        Object.assign(data, transactionData, inputData);

        return $.ajax({
            method: 'POST',
            url: '/api/adyen/klarna',
            headers: {
                Accept: 'application/json',
            },
            data: data,
        });
    }

    function fail(error) {
        if (!error) {
            error = 'Could not validate your payment information. Please check below to make sure you have entered in your payment information correctly.';
        }
        InvoicedBillingPortal.util.showError(
            error,
            'invoiced-klarna-errors',
            true
        );
        refreshCardContainer();
    }

    function refreshCardContainer() {
        $('#klarna-card-container').remove();
        $('#klarna-card-data').before('<div class="mb-3" id="klarna-card-container"></div>');

        loadAdyenDropIn();
    }
})();
