/* globals heap, InvoicedConfig */
(function () {
    'use strict';

    window.InvoicedBillingPortal = {
        bootstrap: bootstrap,
        payments: {
            capture: capturePaymentMethod,
            hideOtherForms: hideOtherForms,
            isRegistered: paymentMethodIsRegistered,
            onSelected: notifyOnSelectedPaymentSource,
            register: registerPaymentMethod,
            registerType: registerPaymentMethodType,
            selectedSource: selectedPaymentSource,
            setSubmitHandler: setSubmitHandler,
            submit: submitPayment,
            tokenizeCard: tokenizeCard,
        },
        flywire: {
            loadFlywireCheckoutJs: loadFlywireCheckoutJs,
        },
        stripe: {
            loadStripeJsV3: loadStripeJsV3,
        },
        util: {
            calcSubscriptionTotal: calcSubscriptionTotal,
            encodeToForm: encodeToForm,
            formatMoney: formatMoney,
            getCookie: getCookie,
            getCurrentLanguage: getCurrentLanguage,
            getJsonValue: getJsonValue,
            getPageData: getPageData,
            getPaymentAmount: getPaymentAmount,
            hideErrors: hideErrors,
            hideLoading: hideLoading,
            initAddressForm: initAddressForm,
            initCardForm: initCardForm,
            nl2br: nl2br,
            showError: showError,
            showLoading: showLoading,
        },
    };

    /*
    ----
    Configuration
    ----
    */

    const configUrl = '/_bootstrap';
    const flywireCheckoutJsUrl = 'https://checkout.flywire.com/flywire-payment.js';
    let stripeJsV3Url = 'https://js.stripe.com/v3';
    const tokenizeEndpointProd = 'https://payments.invoiced.com/tokens';
    const tokenizeEndpointStaging = 'https://payments.staging.invoiced.com/tokens';

    let config;
    let bootstrapListeners = [];
    let bootstrapping;
    let submitHandler;
    function bootstrap(cb) {
        if (config) {
            if (typeof cb === 'function') {
                cb(config);
            }

            return;
        }

        if (typeof cb === 'function') {
            bootstrapListeners.push(cb);
        }

        if (!bootstrapping) {
            bootstrapping = true;
            $(function () {
                $.getJSON(configUrl, function (_config) {
                    config = _config;

                    initPaymentMethods();

                    for (let i in bootstrapListeners) {
                        if (bootstrapListeners.hasOwnProperty(i)) {
                            bootstrapListeners[i](config);
                        }
                    }
                });
            });
        }
    }

    /*
    ----
    Mailto links
    ----
    */

    $(function () {
        $('.mailto-bp').click(function (e) {
            bootstrap(function (config2) {
                window.location = 'mailto:' + config2.email;
            });

            e.preventDefault();
            return false;
        });

        bootstrap(initHeap);
    });

    /*
    ----
    Localization
    ----
    */

    // usage: formatMoney(number, decimalPlaces, decimalSeparator, thousandsSeparator)
    // defaults: (n, 2, ",", ".")
    function formatMoney(number, places, decimal, thousand) {
        places = !isNaN((places = Math.abs(places))) ? places : 2;
        thousand = typeof thousand !== 'undefined' ? thousand : ',';
        decimal = typeof decimal !== 'undefined' ? decimal : '.';
        let negative = number < 0 ? '-' : '';
        let i = parseInt((number = Math.abs(+number || 0).toFixed(places)), 10) + '';
        let j = i.length > 3 ? i.length % 3 : 0;
        return (
            negative +
            (j ? i.substr(0, j) + thousand : '') +
            i.substr(j).replace(/(\d{3})(?=\d)/g, '$1' + thousand) +
            (places
                ? decimal +
                  Math.abs(number - i)
                      .toFixed(places)
                      .slice(2)
                : '')
        );
    }

    /*
    ----
    Payments
    ----
    */

    let paymentMethodTypes = {};
    function registerPaymentMethodType(type, cb) {
        if (typeof paymentMethodTypes[type] !== 'undefined') {
            throw 'Payment method type "' + type + '" already exists!';
        }

        paymentMethodTypes[type] = cb;
    }

    let paymentMethods = {};
    function registerPaymentMethod(method, paymentMethod) {
        if (typeof paymentMethods[method] !== 'undefined') {
            throw 'Payment method "' + method + '" already exists!';
        }

        paymentMethods[method] = paymentMethod;
    }

    function initPaymentMethods() {
        // Register each type of payment method.
        // This is a new style of payment method logic
        // that is not yet implemented by every payment method type.
        $('.register-payment-method').each(function () {
            const paymentMethodData = getJsonValueByEl($(this)) || {};
            const type = paymentMethodData.type;

            if (typeof paymentMethodTypes[type] === 'undefined') {
                throw 'Payment method type "' + type + '" is not registered';
            } else {
                paymentMethodTypes[type](paymentMethodData.paymentMethod, paymentMethodData.config);
            }
        });

        for (let i in paymentMethods) {
            if (paymentMethods.hasOwnProperty(i)) {
                paymentMethods[i].init(config);
            }
        }
    }

    function paymentMethodIsRegistered(method) {
        return typeof paymentMethods[method] !== 'undefined';
    }

    function capturePaymentMethod(method, parameters, success, failure) {
        if (typeof paymentMethods[method] === 'undefined') {
            throw 'Payment method "' + method + '" has not been registered!';
        }

        if (typeof failure !== 'function') {
            failure = function () {};
        }

        paymentMethods[method].capture(parameters, success, failure);
    }

    // This will allow a listener to be notified
    // when a payment source is selected. This is
    // useful when a payment form wants to auto-submit
    // once the user has selected a bank account.
    let paymentSourceListeners = [];
    function notifyOnSelectedPaymentSource(cb) {
        paymentSourceListeners.push(cb);
    }

    // Call this whenever a payment source has been
    // selected by the user. It will fire all the
    // listeners registered in notifyOnSelectedPaymentSource()
    function selectedPaymentSource() {
        for (let i in paymentSourceListeners) {
            if (paymentSourceListeners.hasOwnProperty(i)) {
                paymentSourceListeners[i]();
            }
        }
    }

    function hideOtherForms(paymentMethodId) {
        $('.payment-method-form').addClass('hidden');
        // Disable the form controls of other payment methods to prevent
        // a hidden required field from blocking the form submission.
        $('.payment-method-form input,.payment-method-form select,.payment-method-form textarea').prop(
            'disabled',
            true
        );

        const methodClassName = '.payment-method-form.' + paymentMethodId.replaceAll(':', '_');
        $(methodClassName).removeClass('hidden');
        $(methodClassName + ' input,' + methodClassName + ' select,' + methodClassName + ' textarea').prop(
            'disabled',
            false
        );
    }

    /*
    ----
    Invoiced Payments
    ----
    */

    function tokenizeCard(number, cvc, exp_month, exp_year, name, address, cb) {
        // first load the global config
        bootstrap(function (config2) {
            exp_month = trimWS(exp_month || '');
            exp_year = trimWS(exp_year || '');

            // strip spaces/dashes from card number
            number = (number || '').replace(/\D/g, '');

            const card = {
                number: number,
                cvc: cvc,
                exp_month: exp_month,
                exp_year: exp_year,
                name: name,
            };

            if (address) {
                card.address = {
                    address1: address.address1,
                    address2: address.address2,
                    city: address.city,
                    state: address.state,
                    postal_code: address.postal_code,
                    country: address.country,
                };
            }

            const env = config2.payments_environment;
            const tokenizeEndpoint = env === 'staging' ? tokenizeEndpointStaging : tokenizeEndpointProd;
            $.ajax({
                method: 'POST',
                url: tokenizeEndpoint,
                data: {
                    key: config2.payments_publishable_key,
                    card: card,
                },
                headers: {
                    Accept: 'application/json',
                },
            })
                .done((response, statusCode, xhr) => {
                    cb(xhr.status, response);
                })
                .fail(xhr => {
                    let response = xhr.responseJSON;
                    if (!response) {
                        response = {
                            type: 'api_error',
                            message: 'An unknown error occurred while processing your payment information.',
                        };
                    }

                    cb(xhr.status, response);
                });
        });
    }

    // This submits a billing portal payment via AJAX.
    function setSubmitHandler(cb) {
        submitHandler = cb;
    }

    function submitPayment(paymentSource, success, fail) {
        if (typeof submitHandler !== 'function') {
            throw 'Payment form submit handler has not been set.';
        }

        if (typeof success !== 'function') {
            success = () => {};
        }

        if (typeof fail !== 'function') {
            fail = () => {};
        }

        submitHandler(paymentSource, success, fail);
    }

    /*
    ----
    Flywire
    ----
    */

    let flywireCheckoutJsLoaded = false;
    function loadFlywireCheckoutJs(cb) {
        if (flywireCheckoutJsLoaded) {
            if (typeof cb === 'function') {
                cb();
            }
            return;
        }

        $.getScript(flywireCheckoutJsUrl, function () {
            flywireCheckoutJsLoaded = true;
            if (typeof cb === 'function') {
                cb();
            }
        });
    }

    /*
    ----
    Stripe
    ----
    */

    let stripeJsV3Loaded = false;
    function loadStripeJsV3(cb) {
        if (stripeJsV3Loaded) {
            cb();
            return;
        }

        $.getScript(stripeJsV3Url, function () {
            stripeJsV3Loaded = true;
            cb();
        });
    }

    /*
    ----
    Helpers
    ----
    */

    function initCardForm(class_number, class_expiry, class_cvc) {
        class_number = class_number || 'cc-num';
        class_expiry = class_expiry || 'cc-exp';
        class_cvc = class_cvc || 'cc-cvc';

        $('.' + class_number).payment('formatCardNumber');
        $('.' + class_expiry).payment('formatCardExpiry');
        $('.' + class_cvc).payment('formatCardCVC');

        $('.' + class_number).keyup(function () {
            let cardType = $.payment.cardType($(this).val());
            $('.input-card-number .type').attr('class', 'type ' + cardType);
            if (cardType) {
                $('.card-logos').addClass('brand-selected');
            } else {
                $('.card-logos').removeClass('brand-selected');
            }
        });
    }

    function initAddressForm(section) {
        let selectedCountry = $('select.country-selector[data-section="' + section + '"]').val();
        selectCountry(selectedCountry, section);

        $('.country-selector.' + section).change(function () {
            let id = $(this).val();
            // let section = $(this).data('section');
            selectCountry(id, section);
        });
    }

    function selectCountry(id, section) {
        let country = false;
        for (let i in InvoicedConfig.countries) {
            if (InvoicedConfig.countries[i].code === id) {
                country = InvoicedConfig.countries[i];
                break;
            }
        }

        if (!country) {
            return;
        }

        let states = typeof country.states !== 'undefined' ? country.states : false;
        let sectionClass = '.address.' + section;

        let stateSelect = $('.state-select', sectionClass);
        let stateText = $('.state-text', sectionClass);
        let selectedState = stateText.val();

        if (states) {
            // show a list of selectable states
            let html = '';
            let found = false;
            for (let j in states) {
                if (states.hasOwnProperty(j)) {
                    let state = states[j];
                    html += '<option value="' + state.code + '">' + state.name + '</option>';

                    if (state.code === selectedState) {
                        found = true;
                    }
                }
            }

            // default choice - first in list
            if (!found) {
                selectedState = states[0].code;
            }

            let selectEl = $('select', stateSelect);
            selectEl.html(html).val(selectedState);

            stateSelect.removeClass('hidden');
            stateText.addClass('hidden').val(selectedState);

            // update text input as states are selected
            selectEl.change(function () {
                stateText.val($(this).val());
            });
        } else {
            // show an input
            stateSelect.addClass('hidden');
            stateText.removeClass('hidden').val(selectedState);
        }
    }

    function showError(msg, _class, showPrevious) {
        if (!showPrevious) {
            hideErrors();
        }
        hideLoading();

        _class = _class || 'errors';
        $('.' + _class)
            .text(msg)
            .removeClass('hidden');

        window.scrollTo(0, 0);
    }

    function hideErrors() {
        $('.errors').addClass('hidden');
    }

    function showLoading(message) {
        window.loading_screen = window.pleaseWait({
            backgroundColor: config.highlight_color,
            loadingHtml:
                "<p class='loading-message'>" +
                message +
                "</p><div class='spinner'><div class='dot1'></div><div class='dot2'></div></div>",
            template:
                "<div class='pg-loading-inner'>\n <div class='pg-loading-center-outer'>\n <div class='pg-loading-center-middle'>\n <h1 class='pg-loading-logo-header'>\n </h1>\n <div class='pg-loading-html'>\n </div>\n </div>\n </div>\n</div>",
        });
    }

    function hideLoading() {
        if (window.loading_screen) {
            window.loading_screen.finish();
        }
    }

    function calcSubscriptionTotal(plan, quantity, address, addons, discounts, success, failure) {
        $.ajax({
            method: 'POST',
            url: '/api/subscriptions/preview',
            data: {
                plan: plan.id,
                quantity: quantity,
                shipping: address,
                addons: addons,
                discounts: discounts,
            },
            headers: {
                Accept: 'application/json',
            },
        })
            .done(success)
            .fail(failure);
    }

    function getPaymentAmount() {
        return $('#amount').val();
    }

    // encodes an object to a payment form
    // build a hidden input for each field
    function encodeToForm(obj, namespace) {
        namespace = namespace || '';

        let html = '';
        for (let i in obj) {
            if (obj.hasOwnProperty(i)) {
                let name = i;
                if (namespace) {
                    name = namespace + '[' + i + ']';
                }

                html += '<input type="hidden" name="' + name + '" value="' + obj[i] + '" />';
            }
        }

        return html;
    }

    // Converts a string's newline characters to <br> elements
    function nl2br(input) {
        if (typeof input !== 'undefined' && input) {
            input = input.toString();
            if (input.indexOf('<br') !== -1) {
                return input;
            }

            return input.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br />$2');
        } else {
            return '';
        }
    }

    function getCookie(name) {
        let value = '; ' + document.cookie;
        let parts = value.split('; ' + name + '=');
        if (parts.length === 2) {
            return parts.pop().split(';').shift();
        }
    }

    function getPageData() {
        // Read the JSON-formatted data from the DOM.
        let element = document.getElementById('customerPortalPageData');
        if (!element) {
            return null;
        }

        let contents = element.textContent || element.innerText;
        if (!contents) {
            return null;
        }

        return JSON.parse(contents);
    }

    function getCurrentLanguage() {
        // Read the JSON-formatted data from the DOM.
        let element = document.getElementById('customerPortalLanguage');
        if (!element) {
            return { name: 'English', code: 'en' };
        }

        let contents = element.textContent || element.innerText;
        if (!contents) {
            return { name: 'English', code: 'en' };
        }

        return JSON.parse(contents);
    }

    function getJsonValue(elId) {
        return getJsonValueByEl($('#' + elId));
    }

    function getJsonValueByEl(el) {
        const jsonText = el.text();
        try {
            if (jsonText) {
                return JSON.parse(jsonText) || null;
            }
        } catch (err) {
            // do nothing
        }

        return null;
    }

    function trimWS(str) {
        return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
    }

    function initHeap(config2) {
        /* jshint ignore:start */
        (window.heapReadyCb = window.heapReadyCb || []),
            (window.heap = window.heap || []),
            (heap.load = function (e, t) {
                (window.heap.envId = e),
                    (window.heap.clientConfig = t = t || {}),
                    (window.heap.clientConfig.shouldFetchServerConfig = !1);
                let a = document.createElement('script');
                (a.type = 'text/javascript'),
                    (a.async = !0),
                    (a.src = 'https://cdn.us.heap-api.com/config/' + e + '/heap_config.js');
                let r = document.getElementsByTagName('script')[0];
                r.parentNode.insertBefore(a, r);
                let n = [
                        'init',
                        'startTracking',
                        'stopTracking',
                        'track',
                        'resetIdentity',
                        'identify',
                        'getSessionId',
                        'getUserId',
                        'getIdentity',
                        'addUserProperties',
                        'addEventProperties',
                        'removeEventProperty',
                        'clearEventProperties',
                        'addAccountProperties',
                        'addAdapter',
                        'addTransformer',
                        'addTransformerFn',
                        'onReady',
                        'addPageviewProperties',
                        'removePageviewProperty',
                        'clearPageviewProperties',
                        'trackPageview',
                    ],
                    i = function (e) {
                        return function () {
                            let t = Array.prototype.slice.call(arguments, 0);
                            window.heapReadyCb.push({
                                name: e,
                                fn: function () {
                                    heap[e] && heap[e].apply(heap, t);
                                },
                            });
                        };
                    };
                for (let p = 0; p < n.length; p++) heap[n[p]] = i(n[p]);
            });
        /* jshint ignore:end */

        heap.load(config2.heap_project_id);
        const heapUserId = $('#heapUserId').data('id');
        if (heapUserId) {
            heap.identify(heapUserId);
        }
    }
})();
