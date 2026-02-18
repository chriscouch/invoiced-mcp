/* globals InvoicedBillingPortal */
(function () {
    'use strict';

    let cardComponent,
        paymentParameters,
        paymentSourceSuccess,
        paymentSourceFailure,
        adyenData = null;
    let stateIsValid = false;

    const paymentMethod = {
        init: init,
        capture: capture,
    };

    InvoicedBillingPortal.payments.register('credit_card', paymentMethod);

    function init() {
        adyenData = InvoicedBillingPortal.util.getJsonValue('adyen-card-data');
        loadAdyenDropIn();
    }

    async function loadAdyenDropIn() {
        const configuration = {
            paymentMethodsResponse: adyenData.paymentMethods,
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
                        actions.reject();
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
                    actions.reject();
                }
            },
            onAdditionalDetails: async (state, component, actions) => {
                try {
                    // Make a POST /payments/details request from the backend.
                    const result = await makeDetailsCall(state.data);

                    // If the /payments/details request fails, or if an unexpected error occurs.
                    if (!result.resultCode) {
                        actions.reject();
                        return;
                    }

                    const { resultCode, action, order, donationToken } = result;

                    // If the /payments/details request is successful, resolve whichever of the listed objects are available.
                    actions.resolve({
                        resultCode,
                        action,
                        order,
                        donationToken,
                    });
                } catch (error) {
                    actions.reject();
                }
            },
            onPaymentCompleted: () => {
                paymentSourceSuccess({
                    payment_method: 'card',
                    reference: adyenData.transactionData.reference,
                    forceUnmakeDefault: 1,
                });
            },
            onPaymentFailed: () => {
                if (typeof paymentSourceFailure === 'function') {
                    paymentSourceFailure();
                }
            },
            onError: () => {
                if (typeof paymentSourceFailure === 'function') {
                    paymentSourceFailure();
                }
            },
            onChange: state => {
                stateIsValid = state.isValid;
            },
        };

        const dropInConfiguration = {
            paymentMethodsConfiguration: {
                card: {
                    hasHolderName: true,
                    holderNameRequired: true,
                    billingAddressRequired: true,
                    showPayButton: false,
                    onLoad: () => {
                        // Once the card form is loaded then call hideOtherForms() again
                        // in order to disable the card form elements if it has not been selected
                        const selectedMethod = $('#paymentMethodSelector').val();
                        if (selectedMethod) {
                            InvoicedBillingPortal.payments.hideOtherForms(selectedMethod);
                        }
                    },
                    data: {
                        billingAddress: {
                            street: adyenData.customerAddress.address1,
                            houseNumberOrName: adyenData.customerAddress.address2,
                            postalCode: adyenData.customerAddress.postal_code,
                            city: adyenData.customerAddress.city,
                            country: adyenData.customerAddress.country,
                            stateOrProvince: adyenData.customerAddress.state,
                        },
                    },
                },
            },
            instantPaymentTypes: ['applepay', 'googlepay'],
        };

        const { AdyenCheckout, Dropin } = window.AdyenWeb;
        const checkout = await AdyenCheckout(configuration);
        cardComponent = new Dropin(checkout, dropInConfiguration).mount('#adyen-card-container');
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

        paymentParameters = formParameters;

        paymentSourceSuccess = onSuccess;
        paymentSourceFailure = onFailure;
        cardComponent.submit();
    }

    async function makePaymentsCall(data, transactionData) {
        //for some reason this is not working on iphone, so we need to reparse initial data
        if (typeof transactionData === 'undefined') {
            transactionData = InvoicedBillingPortal.util.getJsonValue('adyen-card-data').transactionData;
        }
        // TODO: need to handle custom amount from payment link
        Object.assign(data, transactionData);
        if (paymentParameters.email) {
            data.shopperEmail = paymentParameters.email;
        }
        const makeDefault = $('#makeDefault');
        const enrollAutoPay = $('#enrollAutoPay');
        const forceEnrollAutoPay = $('#paymentEnrollAutoPay');
        const paymentMakeDefault = $('#paymentMakeDefault');
        if (typeof paymentParameters.formData !== 'undefined' && paymentParameters.formData) {
            data._formData = paymentParameters.formData;
            const receiptEmail = $('#receiptEmailForm').val();

            if (receiptEmail) {
                data._formData += '&receipt_email=' + encodeURIComponent(receiptEmail);
            }

            if (paymentParameters.makeDefault || parseInt(paymentMakeDefault.val()) === 1) {
                data._formData = data._formData.replace(/make_default=0/g, 'make_default=1');
            }

            if (paymentParameters.enrollInAutoPay || parseInt(forceEnrollAutoPay.val()) === 1) {
                data._formData = data._formData.replace(/enroll_autopay=0/g, 'enroll_autopay=1');
            }
        }

        //we should not submit this property to the API
        //because Adyen do not support sync vaulting
        makeDefault.prop('checked', false);
        enrollAutoPay.prop('checked', false);
        forceEnrollAutoPay.val(0);
        paymentMakeDefault.val(0);

        return $.ajax({
            method: 'POST',
            url: '/api/adyen/payments',
            headers: {
                Accept: 'application/json',
            },
            data: data,
        });
    }

    async function makeDetailsCall(data) {
        data.reference = adyenData.transactionData.reference;

        return $.ajax({
            method: 'POST',
            url: '/api/adyen/payments/details',
            headers: {
                Accept: 'application/json',
            },
            data: {
                data: data,
            },
        });
    }
})();
