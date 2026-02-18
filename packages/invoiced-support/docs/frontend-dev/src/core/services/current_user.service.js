/* globals zE */
(function () {
    'use strict';

    angular.module('app.core').factory('CurrentUser', CurrentUserService);

    CurrentUserService.$inject = [
        '$resource',
        '$http',
        '$cookies',
        '$cacheFactory',
        '$log',
        '$window',
        '$injector',
        '$translate',
        'InvoicedConfig',
        'selectedCompany',
        'BrowsingHistory',
        'NpsSurvey',
    ];

    function CurrentUserService(
        $resource,
        $http,
        $cookies,
        $cacheFactory,
        $log,
        $window,
        $injector,
        $translate,
        InvoicedConfig,
        selectedCompany,
        BrowsingHistory,
        NpsSurvey,
    ) {
        let CurrentUser = {
            profile: {},
            companies: [],
            clear: clear,
            useCompany: useCompany,
            setSelectedCompanyId: setSelectedCompanyId,
            hasSignedIn: hasSignedIn,
        };

        let parsedBootstrapResponse = false;

        angular.extend(
            CurrentUser,
            $resource(
                InvoicedConfig.baseUrl + '/users',
                {},
                {
                    ping: {
                        method: 'GET',
                        url: InvoicedConfig.baseUrl + '/ping',
                        noAuth: true,
                    },
                    bootstrap: {
                        method: 'GET',
                        url: InvoicedConfig.baseUrl + '/users/current/bootstrap',
                        noAuth: true,
                        transformResponse: $http.defaults.transformResponse.concat(function (
                            response,
                            headers,
                            status,
                        ) {
                            if (status !== 200) {
                                return response;
                            }

                            // don't parse or cache anything yet if 2FA verification is needed
                            if (response.two_factor_required) {
                                return response;
                            }

                            response.user = parseUser(response.user);
                            response.companies = parseCompanies(response.companies);

                            CurrentUser.selected_company = response.selected_company;
                            if (CurrentUser.selected_company) {
                                CurrentUser.selected_company._name =
                                    CurrentUser.selected_company.nickname || CurrentUser.selected_company.name;
                                useCompany(CurrentUser.selected_company);
                            }

                            CurrentUser.from_sso = response.from_sso;

                            return response;
                        }),
                    },
                    login: {
                        method: 'POST',
                        noAuth: true,
                        url: InvoicedConfig.baseUrl + '/auth/login',
                        transformResponse: $http.defaults.transformResponse.concat(function (user) {
                            if (user.two_factor_required) {
                                return user;
                            }

                            angular.extend(CurrentUser.profile, user);

                            return user;
                        }),
                    },
                    setup2FAStep1: {
                        method: 'POST',
                        noAuth: true,
                        url: InvoicedConfig.baseUrl + '/auth/2fa/1',
                    },
                    setup2FAStep2: {
                        method: 'POST',
                        noAuth: true,
                        url: InvoicedConfig.baseUrl + '/auth/2fa/2',
                    },
                    remove2FA: {
                        method: 'POST',
                        noAuth: true,
                        url: InvoicedConfig.baseUrl + '/auth/remove_2fa',
                    },
                    verify2FA: {
                        method: 'POST',
                        noAuth: true,
                        url: InvoicedConfig.baseUrl + '/auth/verify_2fa',
                    },
                    requestSms2FA: {
                        method: 'POST',
                        noAuth: true,
                        url: InvoicedConfig.baseUrl + '/auth/request_sms_2fa',
                    },
                    logout: {
                        method: 'POST',
                        noAuth: true,
                        url: InvoicedConfig.baseUrl + '/auth/logout',
                    },
                    register: {
                        method: 'POST',
                        noAuth: true,
                        url: InvoicedConfig.baseUrl + '/auth/register',
                        transformResponse: $http.defaults.transformResponse.concat(function (user) {
                            angular.extend(CurrentUser.profile, user);

                            return user;
                        }),
                    },
                    forgotStep1: {
                        method: 'POST',
                        noAuth: true,
                        url: InvoicedConfig.baseUrl + '/auth/forgot',
                    },
                    forgotStep2: {
                        method: 'POST',
                        noAuth: true,
                        params: {
                            token: '@token',
                        },
                        url: InvoicedConfig.baseUrl + '/auth/forgot/:token',
                    },
                    edit: {
                        method: 'PATCH',
                        noAuth: true,
                        url: InvoicedConfig.baseUrl + '/auth/account',
                        transformResponse: $http.defaults.transformResponse.concat(function (response) {
                            let user = response;

                            angular.extend(CurrentUser.profile, user);

                            return user;
                        }),
                    },
                    activity: {
                        method: 'GET',
                        noAuth: true,
                        url: InvoicedConfig.baseUrl + '/users/current/activity',
                    },
                    supportPin: {
                        method: 'GET',
                        noAuth: true,
                        url: InvoicedConfig.baseUrl + '/users/current/support_pin',
                    },
                },
            ),
        );

        CurrentUser.get = function () {
            let companyId = getDesiredCompany() || CurrentUser.profile.default_company_id;

            return CurrentUser.bootstrap({
                company: companyId,
            });
        };

        return CurrentUser;

        // Parses the current user from the bootstrap API response
        function parseUser(user) {
            // determine the person's name
            if (typeof user.first_name != 'undefined') {
                user.name = $.trim(user.first_name + ' ' + user.last_name);
            } else {
                user.name = 'unknown';
            }

            CurrentUser.profile = user;

            parsedBootstrapResponse = true;

            return user;
        }

        // Parses the current user's companies from the bootstrap API response
        function parseCompanies(companies) {
            angular.forEach(companies, function (company) {
                company._name = company.nickname || company.name || '-Incomplete-';
            });
            companies.sort(function (a, b) {
                return a._name.localeCompare(b._name);
            });
            CurrentUser.companies = companies;

            return companies;
        }

        // Clears out the current user.
        function clear() {
            // write over the selected company in order to clear the cache
            delete $cookies.selectedCompany;

            for (let i in selectedCompany) {
                delete selectedCompany[i];
            }

            // clear cached values
            $cacheFactory.get('$http').removeAll();
            CurrentUser.profile = {};
            CurrentUser.companies = [];
            CurrentUser.selected_company = null;
            parsedBootstrapResponse = false;

            return CurrentUser;
        }

        function hasSignedIn() {
            return parsedBootstrapResponse;
        }

        // Gets the ID of the target company.
        function getDesiredCompany() {
            // Determine the company that we want to select based on the cookie.
            // The `selectedCompany` is set by the dashboard
            // whereas the `_selectedCompany` cookie is set by the parent domain (invoiced.com).
            // The parent cookie, `_selectedCompany`, if set has precedence.
            let desiredCompanyId = $cookies.selectedCompany;

            if ($cookies._selectedCompany && $cookies._selectedCompanyFired != $cookies._selectedCompany) {
                desiredCompanyId = $cookies._selectedCompany;

                // set yet another cookie so that we can ignore
                // the parent cookie on the next request.
                // it's cookie soup....
                $cookies._selectedCompanyFired = $cookies._selectedCompany;
            }

            return desiredCompanyId;
        }

        // Makes a company the currently selected account.
        function useCompany(company) {
            let switchedCompany = selectedCompany.id && selectedCompany.id !== company.id;

            // load the company through the API
            // so that it is marked as accessed.
            // in a future update this data will be
            // used to populate the selectedCompany object
            if (!selectedCompany.id || switchedCompany) {
                let Company = $injector.get('Company');
                Company.current();
            }

            // determine the decimal format
            // TODO the decimal format has been disabled
            // due to a rate calculation but when
            // the decimal-first format is ued
            company.thousands_separator = ',';
            company.decimal_separator = '.';

            company.moneyFormat = {
                use_symbol: !company.show_currency_code,
            };

            // generate auth header with API key
            if (company.dashboard_api_key) {
                let encoded = window.btoa(company.dashboard_api_key + ':');
                company.auth_header = 'Basic ' + encoded;
            }

            // store the selected company, and remember it with a cookie
            angular.extend(selectedCompany, company);
            setSelectedCompanyId(company.id);

            // set up Zendesk chat
            if (typeof zE !== 'undefined') {
                zE('webWidget', 'identify', {
                    name: CurrentUser.profile.name,
                    email: CurrentUser.profile.email,
                    organization: company.name,
                });

                zE('webWidget', 'prefill', {
                    name: {
                        value: CurrentUser.profile.name,
                        readOnly: true,
                    },
                    email: {
                        value: CurrentUser.profile.email,
                        readOnly: true,
                    },
                    organization: {
                        value: company.name,
                        readOnly: true,
                    },
                });
            }

            // clear any company-specific cached data
            if (switchedCompany) {
                $cacheFactory.get('$http').removeAll();
                delete selectedCompany.reports;
                delete selectedCompany.dashboards;
                selectedCompany.themes = false;
                BrowsingHistory.clear();
            }

            // set the active translation
            $translate.use('en_' + company.country);

            // show the Survicate NPS survey popup
            NpsSurvey.show(selectedCompany, CurrentUser.profile);

            return CurrentUser;
        }

        function setSelectedCompanyId(companyId) {
            $cookies.selectedCompany = companyId;
        }
    }
})();
