(function () {
    'use strict';

    angular.module('app.catalog').controller('EditItemController', EditItemController);

    EditItemController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        'selectedCompany',
        'Item',
        'Settings',
        'IdGenerator',
        'Core',
        'CustomField',
        'MetadataCaster',
        'Money',
        'model',
        'Feature',
    ];

    function EditItemController(
        $scope,
        $modal,
        $modalInstance,
        selectedCompany,
        Item,
        Settings,
        IdGenerator,
        Core,
        CustomField,
        MetadataCaster,
        Money,
        model,
        Feature,
    ) {
        if (model) {
            $scope.item = angular.copy(model);

            if ($scope.item.taxes.length > 0) {
                let breakdown = [];
                angular.forEach($scope.item.taxes, function (taxRate) {
                    let line = taxRate.name;
                    line += ': ';
                    if (taxRate.is_percent) {
                        line += taxRate.value + '%';
                    } else {
                        line += Money.currencyFormat(taxRate.value, $scope.item.currency, selectedCompany.moneyFormat);
                    }

                    breakdown.push(line);
                });
                $scope.taxes = breakdown.join(', ');
            }

            $scope.hasDefaultPrice = !!$scope.item.currency;

            if (!$scope.item.currency) {
                $scope.item.currency = selectedCompany.currency;
            }
        } else {
            $scope.item = {
                name: '',
                id: '',
                type: null,
                currency: selectedCompany.currency,
                unit_cost: '',
                description: '',
                gl_account: null,
                discountable: true,
                taxable: true,
                taxes: [],
                avalara_tax_code: '',
                avalara_location_code: '',
                metadata: {},
            };
            $scope.hasDefaultPrice = true;
        }

        $scope.rateListOptions = {
            types: false,
        };

        $scope.company = selectedCompany;
        $scope.shouldGenID = !model.id;
        $scope.isExisting = !!model.id;
        $scope.changePricing = false;
        $scope.hasMultiCurrency = Feature.hasFeature('multi_currency');
        $scope.hasGlAccounts = Feature.hasFeature('gl_accounts');

        loadSettings();
        loadCustomFields();

        $scope.generateID = function (model) {
            if (!$scope.shouldGenID && model.id) {
                return;
            }

            $scope.shouldGenID = true;
            if (!model.name) {
                model.id = '';
                return;
            }

            // generate ID as the user types the name
            // i.e. Invoiced Pro -> invoiced-pro
            model.id = IdGenerator.generate(model.name);
        };

        $scope.addRateModal = function (rates, type) {
            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/add-rate.html',
                controller: 'AddRateController',
                resolve: {
                    currency: function () {
                        return $scope.item.currency;
                    },
                    ignore: function () {
                        return rates;
                    },
                    type: function () {
                        return type;
                    },
                    options: function () {
                        return {};
                    },
                },
                backdrop: 'static',
                keyboard: false,
                windowClass: 'add-rate-modal',
            });

            modalInstance.result.then(
                function (rate) {
                    rates.push(rate);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.save = function (item) {
            $scope.saving = true;
            $scope.error = null;

            item = angular.copy(item);

            // parse price
            if (!$scope.hasDefaultPrice) {
                item.currency = null;
                item.unit_cost = 0;
            }

            // parse G/L account
            if (item.gl_account && typeof item.gl_account == 'object') {
                item.gl_account = item.gl_account.code;
            }

            // parse tax rates
            angular.forEach(item.taxes, function (rate, index) {
                item.taxes[index] = rate.id;
            });

            // parse metadata
            MetadataCaster.marshalForInvoiced('item', item.metadata, function (metadata) {
                item.metadata = metadata;

                if ($scope.isExisting) {
                    if ($scope.changePricing) {
                        deleteAndCreate(item);
                    } else {
                        saveExisting(item);
                    }
                } else {
                    saveNew(item);
                }
            });
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.generateID($scope.item);

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function saveExisting(item) {
            Item.edit(
                {
                    id: item.id,
                },
                {
                    name: item.name,
                    type: item.type,
                    description: item.description,
                    metadata: item.metadata,
                },
                function (_item) {
                    $scope.saving = false;
                    $modalInstance.close(_item);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function deleteAndCreate(item) {
            Item.delete(
                {
                    id: item.id,
                },
                function () {
                    saveNew({
                        id: item.id,
                        name: item.name,
                        type: item.type,
                        currency: item.currency,
                        unit_cost: item.unit_cost,
                        description: item.description,
                        gl_account: item.gl_account,
                        discountable: item.discountable,
                        taxable: item.taxable,
                        taxes: item.taxes,
                        avalara_tax_code: item.avalara_tax_code,
                        avalara_location_code: item.avalara_location_code,
                        metadata: item.metadata,
                    });
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function saveNew(item) {
            Item.create(
                item,
                function (_item) {
                    $scope.saving = false;
                    $modalInstance.close(_item);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function loadSettings() {
            Settings.accountsReceivable(function (settings) {
                $scope.settings = settings;
            });
        }

        function loadCustomFields() {
            CustomField.all(
                function (customFields) {
                    $scope.customFields = [];
                    angular.forEach(customFields, function (customField) {
                        // All type custom fields are intentionally excluded here
                        if (customField.object === 'item') {
                            $scope.customFields.push(customField);
                        }
                    });
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
