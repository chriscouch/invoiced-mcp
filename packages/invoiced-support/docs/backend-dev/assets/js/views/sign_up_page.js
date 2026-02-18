/* globals InvoicedBillingPortal, InvoicedConfig */
(function () {
    'use strict';

    InvoicedBillingPortal.bootstrap(function (config) {
        var url = $('#signupForm').data('url') + '/_bootstrap';
        $.getJSON(url, function (result) {
            run(config, result.plans);
        });
    });

    function run(config, plans) {
        var selectedPlan = $('.plan-selector').val();
        var planQuantity = parseInt($('.plan-quantity').val());

        hideIncompatibleAddons();
        var addons = getSelectedAddons();
        var discounts = [];
        var paymentMethod;
        var pageType = $('#signUpPageType').val();

        var requiredAddressProperties = ['address1', 'city', 'state', 'postal_code', 'country'];
        var assessedAddress = {};
        var hasShippingAddress = $('.address.shipping').length > 0;

        var couponCode = $('#couponCode').val();
        if (couponCode) {
            lookupCouponCode(couponCode);
        }

        updateTotal();

        $('.plan-selector').change(onPlanChange);
        $('.plan-quantity').change(onPlanChange);
        $('.addon-enabled').change(onAddonChange);
        $('.addon-qty').change(onAddonChange);
        $('input', '.address.billing').change(onAddressInputChange);
        $('select', '.address.billing').change(onAddressInputChange);

        if (hasShippingAddress) {
            $('input', '.address.shipping').change(onAddressInputChange);
            $('select', '.address.shipping').change(onAddressInputChange);
        }

        $('.coupon-code-link').click(function (e) {
            e.preventDefault();
            $('.coupon-code-link').addClass('hidden');
            $('.coupon-code-form').removeClass('hidden');
            $('#couponCode').focus();
        });

        $('.coupon-code-cancel').click(function (e) {
            e.preventDefault();
            $('.coupon-code-link').removeClass('hidden');
            $('.coupon-code-form').addClass('hidden');
        });

        $('#couponCode').keypress(function (e) {
            if (e.keyCode === 13) {
                lookupCouponCode($(this).val());
                e.preventDefault();
            }
        });

        $('.coupon-code-apply').click(function () {
            lookupCouponCode($('#couponCode').val());
        });

        $('.coupon-code-remove').click(removeCoupon);

        InvoicedBillingPortal.util.initAddressForm('billing');
        InvoicedBillingPortal.util.initAddressForm('shipping');

        var method = $('input[name="payment_source[method]"]:checked').val();
        if (!method) {
            method = $('input[name="payment_source[method]"]:first').attr('checked', 'checked').val();
        }
        selectedPaymentMethod(method);

        $('input[name="payment_source[method]"]').change(function () {
            selectedPaymentMethod($(this).val());
        });

        $('#acceptToS').change(function () {
            var accepted = $(this).is(':checked');
            if (accepted) {
                $('.signup-form-button button').removeAttr('disabled');
            } else {
                $('.signup-form-button button').attr('disabled', 'disabled');
            }
        });

        var sending = false;
        $('#signupForm').submit(function (e) {
            e.preventDefault();

            if (sending) {
                return;
            }

            InvoicedBillingPortal.util.showLoading($('#paymentProcessingMessage').text());

            if (paymentMethod === '__default__') {
                sending = true;
                payment({})
                    .then(function () {
                        sending = false;
                    })
                    .catch(function () {
                        sending = false;
                    });
            } else {
                // capture the payment information
                const paymentParameters = {
                    email: $('.customer-email').val(),
                    address1: $('.billing-address1').val(),
                    address2: $('.billing-address2').val(),
                    city: $('.billing-city').val(),
                    state: $('.billing-state').val(),
                    postal_code: $('.billing-postal-code').val(),
                    country: $('.billing-country').val(),
                    formData: serializeForm({}),
                };

                InvoicedBillingPortal.payments.capture(
                    paymentMethod,
                    paymentParameters,
                    capturedPaymentMethod,
                    paymentMethodFailed
                );
            }
        });

        function capturedPaymentMethod(paymentSource) {
            sending = true;
            payment(
                paymentSource,
                function () {
                    sending = false;
                },
                function () {
                    sending = false;
                }
            );
        }

        function paymentMethodFailed(wasCanceled) {
            InvoicedBillingPortal.util.hideLoading();
            if (!wasCanceled) {
                InvoicedBillingPortal.util.showError(
                    'Could not validate your payment information. Please check below to make sure you have entered in your payment information correctly.',
                    'sign-up-page-errors',
                    true
                );
            }
        }

        function payment(paymentSource, resolve, reject) {
            // NOTE: not using promises here because they
            // are not supported in IE
            var url = $('#signupForm').attr('action');
            $.ajax({
                method: 'POST',
                url: url,
                data: serializeForm(paymentSource),
                headers: {
                    Accept: 'application/json',
                },
            })
                .then(function (data) {
                    window.location.href = data.url;
                    if (typeof resolve === 'function') {
                        resolve();
                    }
                })
                .fail(function (data) {
                    // show the error message
                    var message;
                    try {
                        message = JSON.parse(data.responseText).error;
                    } catch (err) {
                        message = 'An unknown error has occurred';
                    }
                    InvoicedBillingPortal.util.showError(message, 'sign-up-page-errors');
                    if (typeof reject === 'function') {
                        reject();
                    }
                });
        }

        /**
         * Handler for plan selection / quantity changes.
         */
        function onPlanChange() {
            selectedPlan = $('.plan-selector').val();
            planQuantity = parseInt($('.plan-quantity').val());

            updateTotal();
        }

        /**
         * Hides recurring plan addons which have intervals
         * that don't match that of the current selected plan.
         *
         * E.g. Plan recurs monthly, addon recurs weekly.
         */
        function hideIncompatibleAddons() {
            // autopay pages do not have addons
            if (pageType === 'autopay') {
                return;
            }

            var plan = getSelectedPlan();

            $('.addon.recurring').addClass('hidden');
            $('.addon.recurring[data-interval="' + plan.interval_str + '"]').removeClass('hidden');

            var visibleAddons = $('.addon:not(.hidden)').length;
            if (visibleAddons > 0) {
                $('.addons').removeClass('hidden');
            } else {
                $('.addons').addClass('hidden');
            }
        }

        /**
         * Handler for address input changes.
         */
        function onAddressInputChange() {
            if (didAddressChange()) {
                updateTotal();
            }
        }

        /**
         * Returns whether or not the address has changed.
         *
         * NOTE: Always returns false if the address is
         * invalid.
         */
        function didAddressChange() {
            var taxAddressSelector = '.address.billing';
            if (hasShippingAddress) {
                taxAddressSelector = '.address.shipping';
            }

            var taxAddress = {
                address1: $('.billing-address1', taxAddressSelector).val(),
                address2: $('.billing-address2', taxAddressSelector).val() || null,
                city: $('.billing-city', taxAddressSelector).val(),
                state: $('.billing-state', taxAddressSelector).val(),
                postal_code: $('.billing-postal-code', taxAddressSelector).val(),
                country: $('.billing-country', taxAddressSelector).val(),
            };

            // check address validity
            var isValid = true;
            for (var i in requiredAddressProperties) {
                if (requiredAddressProperties.hasOwnProperty(i)) {
                    if (!taxAddress[requiredAddressProperties[i]]) {
                        isValid = false;
                        break;
                    }
                }
            }

            // check if any address components have changed since the last assessment
            var isDifferent = false;
            for (i in taxAddress) {
                if (taxAddress[i] !== assessedAddress[i]) {
                    isDifferent = true;
                    break;
                }
            }

            var didChange = isValid && isDifferent;
            if (didChange) {
                assessedAddress = taxAddress;
            }

            return didChange;
        }

        /**
         * Updates total, taxes and discounts
         */
        function updateTotal() {
            // autopay pages do not have a total
            if (pageType === 'autopay') {
                return;
            }

            var plan = getSelectedPlan();

            // show currency symbol
            var c = plan.currency.toUpperCase();
            var currencySymbol = InvoicedConfig.currencies[c].symbol;
            $('.signup-form-total .currency').html(currencySymbol);

            // calculate total
            var recurringAddons = [];
            var oneTimeCharges = [];
            for (var i in addons) {
                if (addons.hasOwnProperty(i)) {
                    var addon = addons[i];
                    if (addon.recurring) {
                        // exclude addons that do not match the selected billing interval
                        if (addon.interval_str && addon.interval_str !== plan.interval_str) {
                            continue;
                        }
                        recurringAddons.push(addon);
                    } else {
                        oneTimeCharges.push(addon);
                    }
                }
            }

            var discountIds = discounts.map(function (discount) {
                return discount.id;
            });
            // Request subscription preview from Invoiced
            InvoicedBillingPortal.util.calcSubscriptionTotal(
                plan,
                planQuantity,
                assessedAddress,
                recurringAddons,
                discountIds,
                function (result) {
                    // update total
                    var total = InvoicedBillingPortal.util.formatMoney(result.total).replace('.00', '');
                    $('.signup-form-total .total').html(total);
                    $('.signup-form-total .interval').html(plan.interval_str);
                    $('.billing-interval').html(plan.interval_str);

                    // one-time charges
                    var upFrontTotal = 0; // not used currently
                    var html = '';
                    for (var j in oneTimeCharges) {
                        if (oneTimeCharges.hasOwnProperty(j)) {
                            var addon2 = oneTimeCharges[j];
                            upFrontTotal += addon2.amount;

                            // total line and strip a trailing ".00"
                            var lineTotal = addon2.amount * addon2.quantity;
                            lineTotal = InvoicedBillingPortal.util.formatMoney(lineTotal).replace('.00', '');

                            // build line items for display
                            html += '<div class="signup-form-line-item setup-fee">';
                            html += '+ ' + currencySymbol + lineTotal + ' ' + addon2.name + ' (charged now)';
                            html += '</div>';
                        }
                    }
                    $('.signup-form-line-items').html(html);

                    // update taxes
                    updateTaxDetails(currencySymbol, result.taxes, result.has_tax_id, result.tax_id_label);
                },
                function () {
                    // Do nothing
                }
            );
        }

        /**
         * Returns all currently enabled addons.
         */
        function getSelectedAddons() {
            var enabledAddons = [];
            $('.addon-enabled').each(function () {
                var $this = $(this);
                var id = $this.data('addon');

                // check if addon is enabled
                var enabled = !($this.attr('type') === 'checkbox' && !$this.is(':checked'));
                if (enabled) {
                    $('.addon-qty-holder-' + id).removeClass('hidden');
                } else {
                    $('.addon-qty-holder-' + id).addClass('hidden');
                    return;
                }

                // check if addon is hidden
                if ($this.parents('.addon').hasClass('hidden')) {
                    return;
                }

                var addon = {
                    name: $this.data('name'),
                    quantity: parseInt($('.addon-qty-' + id).val()),
                    amount: parseFloat($this.data('amount')),
                    recurring: !!$this.data('recurring'),
                    taxable: !!$this.data('taxable'),
                    discountable: !!$this.data('discountable'),
                    interval_str: $this.data('interval'),
                };

                // add plan id to addon
                var idParts = id.split('-'); // ids are formatted as "catalog_item-{id}" or "plan-{id}"
                if (addon.recurring && idParts.length > 0 && idParts[0] === 'plan') {
                    addon.plan = idParts.slice(1).join('-');
                }

                enabledAddons.push(addon);
            });

            return enabledAddons;
        }

        /**
         * Handler for when addon selections change.
         */
        function onAddonChange() {
            addons = getSelectedAddons();
            updateTotal();
        }

        /**
         * Updates the inclusive, exclusive tax detail labels.
         */
        function updateTaxDetails(currencySymbol, taxes, hasTaxId, taxIdLabel) {
            if (!taxes) {
                $('.signup-form-taxes').addClass('hidden');
            } else {
                $('.signup-form-taxes').removeClass('hidden');

                var amountString = currencySymbol + InvoicedBillingPortal.util.formatMoney(taxes).replace('.00', '');
                $('#tax-amount').html(amountString);
            }

            // conditionally show the tax ID field
            if (hasTaxId) {
                $('#taxIdLabel').html(taxIdLabel);
                $('.tax-id-field').removeClass('hidden');
            } else {
                $('.tax-id-field').addClass('hidden');
            }
        }

        function lookupCouponCode(code) {
            $('#couponCode').val('');
            $('.coupon-code-error').addClass('hidden');
            $('.coupon-code-apply').attr('disabled', 'disabled');

            var url = $('#signupForm').data('url') + '/_lookupCoupon';
            $.ajax({
                method: 'GET',
                url: url,
                data: { id: code },
                headers: {
                    Accept: 'application/json',
                },
                success: function (coupon) {
                    $('.coupon-code-apply').removeAttr('disabled');

                    applyCoupon(coupon);
                },
            }).fail(function (jqXHR) {
                $('.coupon-code-apply').removeAttr('disabled');

                if (jqXHR.status === 404) {
                    $('.coupon-code-error').text('Could not find the coupon code: ' + code);
                } else {
                    $('.coupon-code-error').text('An error occurred looking up the coupon code: ' + code);
                }

                $('.coupon-code-error').removeClass('hidden');
            });
        }

        function applyCoupon(coupon) {
            $('.coupon-code-link').addClass('hidden');
            $('.coupon-code-form').addClass('hidden');
            $('#formCouponCode').val(coupon.id);

            // apply the coupon to the subscription
            discounts = [coupon];
            updateTotal();

            // display the coupon in the UI
            $('.signup-form-line-item.discounts .code').text(coupon.id);
            $('.signup-form-line-item.discounts .value').text(coupon.value_formatted);
            $('.signup-form-line-item.discounts').removeClass('hidden');
        }

        function removeCoupon() {
            $('.coupon-code-link').removeClass('hidden');
            $('.coupon-code-form').addClass('hidden');
            $('#formCouponCode').val('');

            // remove any discounts
            discounts = [];
            updateTotal();

            // update the UI
            $('.signup-form-line-item.discounts').addClass('hidden');
        }

        function selectedPaymentMethod(method2) {
            var convenienceFee = method2 === 'credit_card' || method2.indexOf('card') !== -1;
            if (convenienceFee) {
                $('#convenience-fee-warning').show();
            } else {
                $('#convenience-fee-warning').hide();
            }
            if (method2 === paymentMethod || !method2) {
                return;
            }

            paymentMethod = method2;
            InvoicedBillingPortal.payments.hideOtherForms(method2);
        }

        /**
         * Iterates the list of plans and returns
         * the one that is currently selected.
         */
        function getSelectedPlan() {
            var plan = false;
            for (var i in plans) {
                if (plans[i].id === selectedPlan) {
                    plan = plans[i];
                    break;
                }
            }

            return plan;
        }

        function serializeForm(paymentSource) {
            // encode payment source into form
            var html = InvoicedBillingPortal.util.encodeToForm(paymentSource, 'payment_source');
            $('#paymentSourceData').html(html);

            return $('#signupForm').serialize();
        }
    }
})();
