/* globals confirm, InvoicedBillingPortal */
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
        }
        window.flywirePaymentsInitialized = true;

        function capture(formParameters, onSuccess, onFailure) {
            let prefillAddress = null;
            if (typeof formParameters.address1 !== 'undefined' && formParameters.address1) {
                prefillAddress = (formParameters.address1 + ' ' + formParameters.address2).trim();
            }
            const config = {
                env: flywireViewConfig.environment,
                recipientCode: flywireViewConfig.code,
                amount: 0,
                email: formParameters.email || flywireViewConfig.email || '',
                phone: formParameters.phone || flywireViewConfig.phone || '',
                firstName: formParameters.firstName || flywireViewConfig.firstName || '',
                lastName: formParameters.lastName || flywireViewConfig.lastName || '',
                address: prefillAddress || flywireViewConfig.address || '',
                city: formParameters.city || flywireViewConfig.city || '',
                state: formParameters.state || flywireViewConfig.state || '',
                zip: formParameters.postal_code || flywireViewConfig.zip || '',
                country: formParameters.country || flywireViewConfig.country || '',
                locale: flywireViewConfig.locale,
                recurringType: 'tokenization',
                paymentOptionsConfig: {
                    filters: flywireViewConfig.filters || null,
                    sort: flywireViewConfig.sort || null,
                },
                // Display payer and custom field input boxes
                requestPayerInfo: true,
                skipCompletedSteps: true,
                onCompleteCallback: function (data) {
                    if (data.status !== 'success') {
                        onFailure();
                        return;
                    }

                    onSuccess({
                        token: data.token,
                        type: data.type,
                        expirationYear: data.expirationYear,
                        expirationMonth: data.expirationMonth,
                        digits: data.digits,
                        brand: data.brand,
                    });
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
})();
