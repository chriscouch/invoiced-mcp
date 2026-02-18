(function () {
    'use strict';

    angular.module('app.settings').controller('BusinessProfileSettingsController', BusinessProfileSettingsController);

    BusinessProfileSettingsController.$inject = [
        '$scope',
        '$modal',
        '$window',
        'uploadManager',
        'Company',
        'selectedCompany',
        'InvoicedConfig',
        'CSRF',
        'Core',
        'Feature',
    ];

    function BusinessProfileSettingsController(
        $scope,
        $modal,
        $window,
        uploadManager,
        Company,
        selectedCompany,
        InvoicedConfig,
        CSRF,
        Core,
        Feature,
    ) {
        $scope.company = angular.copy(selectedCompany);

        $scope.percentage = 0;
        $scope.logoUrl = InvoicedConfig.apiBaseUrl + '/companies/' + $scope.company.id + '/logo';
        $scope.onboardingUrl = $scope.company.edit_url;

        $scope.now = new Date();

        $scope.profileSettings = ['username', 'nickname', 'email', 'address_extra', 'tax_id', 'phone', 'website'];

        $scope.brandingSettings = ['highlight_color'];

        $scope.localizationSettings = [
            'currency',
            'multi_currency', // NOTE: this is not an actual company property but a feature flag
            'show_currency_code',
            'date_format',
            'time_zone',
            'language',
        ];

        $scope.hasInternationalization = Feature.hasFeature('internationalization');
        $scope.company.multi_currency = Feature.hasFeature('multi_currency');

        $scope.revertSettings = function (settings) {
            angular.forEach(settings, function (param) {
                if (param === 'show_currency_code') {
                    $scope.company.show_currency_code = selectedCompany.show_currency_code ? '1' : '0';
                } else {
                    $scope.company[param] = selectedCompany[param];
                }
            });
        };

        $scope.saveSetting = function (setting, value) {
            $scope.company[setting] = value;
            $scope.saveSettings([setting]);
        };

        $scope.saveSettings = function (settings, section) {
            section = section || false;
            $scope.saving = true;
            $scope.error = null;

            let params = {};
            angular.forEach(settings, function (param) {
                if (param === 'multi_currency') {
                    let enabled = $scope.company.multi_currency;
                    Feature.edit(
                        {
                            id: 'multi_currency',
                        },
                        {
                            enabled: enabled,
                        },
                        function () {
                            $scope.company.multi_currency = enabled;
                        },
                        function (result) {
                            $scope.error = result.data;
                        },
                    );
                } else if (param === 'show_currency_code') {
                    params[param] = $scope.company[param] === '1' ? true : false;
                } else if (param === 'website') {
                    params[param] =
                        $scope.company[param] && $scope.company[param].indexOf('http') !== 0
                            ? 'http://' + $scope.company[param]
                            : $scope.company[param];
                } else {
                    params[param] = $scope.company[param];
                }
            });

            Company.edit(
                {
                    id: $scope.company.id,
                },
                params,
                function (company) {
                    $scope.saving = false;

                    Core.flashMessage('Your settings have been updated.', 'success');

                    if (section) {
                        $scope[section] = false;
                    }

                    company.show_currency_code = company.show_currency_code ? '1' : '0';

                    angular.extend($scope.company, company);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        };

        $scope.resendVerification = function () {
            $scope.resending = true;
            $scope.error = null;

            // retrieve a fresh CSRF token first
            CSRF(function () {
                Company.resendVerificationEmail(
                    {
                        id: selectedCompany.id,
                    },
                    {},
                    function () {
                        $scope.resending = false;
                        $scope.resent = true;
                        Core.flashMessage(
                            'A new verification request has been sent to ' + selectedCompany.email,
                            'success',
                        );
                    },
                    function (result) {
                        $scope.resending = false;
                        $scope.error = result.data;
                    },
                );
            });
        };

        $scope.$on('fileAdded', function () {
            uploadManager.upload();
            $scope.$apply();
        });

        $scope.$on('uploadProgress', function (e, call) {
            $scope.percentage = call;
            $scope.$apply();
        });

        $scope.$on('fileDone', function (e, call) {
            if (call.result.logo) {
                $scope.logoErrorMsg = '';
                angular.extend(selectedCompany, {
                    logo: call.result.logo,
                });
                $scope.company = angular.copy(selectedCompany);
            } else {
                $scope.logoErrorMsg = 'There was an error saving the logo.';
            }
            $scope.$apply();
        });

        $scope.changeCountry = function (country) {
            $scope.hasTaxId = Core.countryHasSellerTaxId(country);
        };

        $scope.showDateFormatDialog = function () {
            $modal.open({
                templateUrl: 'components/views/date-format.html',
                controller: 'ModalController',
            });
        };

        Core.setTitle('Business Profile');

        loadCompany($scope.company.id);
        $scope.changeCountry($scope.company.country);

        function loadCompany(id) {
            Company.find(
                {
                    id: id,
                },
                function (company) {
                    company.show_currency_code = company.show_currency_code ? '1' : '0';

                    angular.extend($scope.company, company);
                },
            );
        }
    }
})();
