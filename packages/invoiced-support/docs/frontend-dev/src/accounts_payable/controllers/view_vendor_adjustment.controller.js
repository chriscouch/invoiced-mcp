/* globals vex */
(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('ViewVendorAdjustmentController', ViewVendorAdjustmentController);

    ViewVendorAdjustmentController.$inject = [
        '$scope',
        '$state',
        '$controller',
        '$rootScope',
        'LeavePageWarning',
        'VendorAdjustment',
        'Core',
        'BrowsingHistory',
    ];

    function ViewVendorAdjustmentController(
        $scope,
        $state,
        $controller,
        $rootScope,
        LeavePageWarning,
        VendorAdjustment,
        Core,
        BrowsingHistory,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = VendorAdjustment;
        $scope.modelTitleSingular = 'Vendor Adjustment';
        $scope.modelObjectType = 'vendor_adjustment';

        //
        // Presets
        //

        $scope.documents = [];
        $scope.documentPage = 1;

        //
        // Methods
        //

        $scope.preFind = function (findParams) {
            findParams.expand = 'vendor,bill,vendor_credit';
        };

        $scope.postFind = function (adjustment) {
            $scope.adjustment = adjustment;

            $rootScope.modelTitle = adjustment.vendor.name;
            Core.setTitle('Adjustment for ' + adjustment.vendor.name);

            BrowsingHistory.push({
                id: adjustment.id,
                type: 'vendor_adjustment',
                title: adjustment.vendor.name,
            });

            return $scope.adjustment;
        };

        $scope.delete = function (adjustment) {
            vex.dialog.confirm({
                message: 'Are you sure you want to void this adjustment? This operation is irreversible.',
                callback: function (result) {
                    if (result) {
                        VendorAdjustment.delete(
                            {
                                id: adjustment.id,
                            },
                            function () {
                                adjustment.voided = true;
                            },
                            function (result) {
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        //
        // Initialization
        //

        $scope.initializeDetailPage();
        Core.setTitle('Vendor Adjustment');
    }
})();
