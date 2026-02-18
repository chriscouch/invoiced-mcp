/* globals InvoicedBillingPortal, InvoicedConfig, confirm, flywireDecorate, btoa */
window.flywireDecorate = () => {};
(function () {
    'use strict';

    // This script can be included multiple times for various payment methods
    // so ensure it is only initialized once.
    if (typeof window.flywirePaymentsInitialized !== 'undefined') {
        return;
    }
    window.flywirePaymentsInitialized = true;

    InvoicedBillingPortal.payments.registerType('flywire_payment', function (paymentMethod, flywireViewConfig) {
        function init() {
            InvoicedBillingPortal.flywire.loadFlywireCheckoutJs();

            if (0 === $('[name="injected_data"]').length) {
                const injectedData = flywireDecorate(flywireViewConfig);
                $('#paymentSubmitForm').append(
                    "<input type='hidden' name='injected_data' value='" + btoa(JSON.stringify(injectedData)) + "'>"
                );
            }
        }

        function capture(formParameters, onSuccess, onFailure) {
            InvoicedBillingPortal.util.hideErrors();
            $.ajax({
                method: 'GET',
                url: '/api/flows/' + flywireViewConfig.callbackId + '/payable',
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
            let prefillAddress = null;
            if (typeof formParameters.address1 !== 'undefined' && formParameters.address1) {
                prefillAddress = (
                    formParameters.address1 +
                    ' ' +
                    (formParameters.address2 ? formParameters.address2 : '')
                ).trim();
            }
            // Tokenization should be enabled if the payment method is being saved as the default
            // or if being enrolled in AutoPay.
            const performTokenization = formParameters.makeDefault || formParameters.enrollInAutoPay;
            const country = formParameters.country || flywireViewConfig.country || '';
            const config = {
                env: flywireViewConfig.environment,
                recipientCode: flywireViewConfig.code,
                amount: formParameters.amount || flywireViewConfig.amount,
                email: formParameters.email || flywireViewConfig.email || '',
                phone: getPhoneNumber(formParameters.phone, country) || flywireViewConfig.phone || '',
                firstName: formParameters.firstName || flywireViewConfig.firstName || '',
                lastName: formParameters.lastName || flywireViewConfig.lastName || '',
                address: prefillAddress || flywireViewConfig.address || '',
                city: formParameters.city || flywireViewConfig.city || '',
                state: formParameters.state || flywireViewConfig.state || '',
                zip: formParameters.postal_code || flywireViewConfig.zip || '',
                country: country,
                locale: flywireViewConfig.locale,
                paymentOptionsConfig: {
                    filters: flywireViewConfig.filters || null,
                    sort: flywireViewConfig.sort || null,
                },
                // Display payer and custom field input boxes
                requestRecipientInfo: false,
                requestPayerInfo: true,
                skipCompletedSteps: true,
                surchargeConfig: {
                    enable: !!flywireViewConfig.surcharging,
                },
                callbackVersion: '2',
                callbackUrl: flywireViewConfig.callbackUrl,
                callbackId: flywireViewConfig.callbackId,
                nonce: flywireViewConfig.nonce,
                recipientFields: {
                    customer_name: flywireViewConfig.customer.name,
                    customer_number: flywireViewConfig.customer.number,
                    invoice_number: flywireViewConfig.documents
                        .filter((document) => document.type === 'invoice' || document.type === 'estimate')
                        .map((document) => document.number)
                        .join(','),
                    invoiced_ref: flywireViewConfig.callbackId,
                },
                paymentMethodProcessingData: flywireViewConfig.additionalData,
                onCompleteCallback: function (data) {
                    if (data.status !== 'success' && data.status !== 'pending') {
                        onFailure();
                        return;
                    }

                    let paymentSource = {
                        nonce: flywireViewConfig.nonce,
                        sig: data.sig,
                        paymentMethod: data.paymentMethod,
                        reference: data.reference,
                        status: data.status,
                        flywireAmount: data.amount,
                    };

                    if (data.token) {
                        paymentSource = Object.assign(paymentSource, {
                            token: data.token,
                            type: data.type,
                            expirationYear: data.expirationYear,
                            expirationMonth: data.expirationMonth,
                            digits: data.digits,
                            brand: data.brand,
                            save_flywire_method: performTokenization,
                        });
                    }

                    onSuccess(paymentSource);
                },
                onInvalidInput: function (errors) {
                    const message = errors.reduce((accumulator, error) => (accumulator + ' ' + error.msg).trim(), '');
                    InvoicedBillingPortal.util.showError(message, 'flywire-errors');
                    onFailure();
                },
                onCancel: function () {
                    onFailure(true);
                },
            };

            const injectedData = flywireDecorate(flywireViewConfig);
            for (let key in injectedData) {
                if (injectedData.hasOwnProperty(key)) {
                    if (injectedData[key] === null) {
                        delete config[key];
                    }
                    config[key] = injectedData[key];
                }
            }

            if (performTokenization) {
                config.recurringType = 'tokenization';
            }

            const modal = window.FlywirePayment.initiate(config);
            modal.render();

            $('.fwpCloseLink').hide();
            $('.fwpBeforeIframe').append(
                '<a style="cursor: pointer; margin-left: auto; box-sizing: content-box; width: 22px; height: 22px; padding: 10px 0 10px 10px;">' +
                    '<div class="fwpCloseSlash"><div class="fwpCloseBackslash"></div></div>' +
                    '</a>'
            );
            $('.fwpBeforeIframe a').click(function () {
                if (confirm(flywireViewConfig.alert)) {
                    modal.close();
                    onFailure(true);
                }
            });
        }

        // Register the instance of this Flywire payment form
        InvoicedBillingPortal.payments.register(paymentMethod, {
            init: init,
            capture: capture,
        });
    });

    function getPhoneNumber(phone, country) {
        if (!phone) {
            return null;
        }

        phone = phone.replace(/[^+\d]/g, '');

        // if the phone is prefixed with a '+' then we will assume it's complete
        if ('+' === phone.substring(0, 1)) {
            return phone;
        }

        const countryData = InvoicedConfig.countries.filter(configCountry => configCountry.code === country);
        if (!countryData.length) {
            return null;
        }
        country = countryData[0];

        if (!country.phone_code) {
            return null;
        }

        const countryCode = country.phone_code;
        const countryCodes = [countryCode].concat(country.alternative_phone_codes || []);
        for (const code of countryCodes) {
            if (phone.startsWith(code)) {
                return '+' + phone;
            }
        }

        return '+' + countryCode + phone;
    }
})();
