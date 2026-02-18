/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('ChasingInvoiceSettingsController', ChasingInvoiceSettingsController);

    ChasingInvoiceSettingsController.$inject = [
        '$scope',
        '$modal',
        'Company',
        'selectedCompany',
        'Core',
        'InvoiceChasingCadence',
        'LeavePageWarning',
        'Feature',
    ];

    function ChasingInvoiceSettingsController(
        $scope,
        $modal,
        Company,
        selectedCompany,
        Core,
        InvoiceChasingCadence,
        LeavePageWarning,
        Feature,
    ) {
        /**
         * Initialization
         */

        $scope.hasFeature = Feature.hasFeature('smart_chasing') && Feature.hasFeature('invoice_chasing');
        $scope.cadences = [];
        $scope.loading = 0;
        $scope.deleting = {};

        Core.setTitle('Invoice Chasing');
        loadCadences();

        /**
         * Sets an InvoiceChasingCadence to be the default or unsets it from default.
         */
        $scope.setDefault = function (cadence, value) {
            InvoiceChasingCadence.edit(
                {
                    id: cadence.id,
                },
                {
                    default: value,
                },
                function (_cadence) {
                    angular.extend(cadence, _cadence);
                    Core.flashMessage('The cadence, ' + cadence.name + ', has been updated', 'success');
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        /**
         * Deletes an InvoiceChasingCadence.
         */
        $scope.delete = function (cadence) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this cadence?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[cadence.id] = true;
                        $scope.error = null;

                        InvoiceChasingCadence.delete(
                            {
                                id: cadence.id,
                            },
                            function () {
                                delete $scope.deleting[cadence.id];

                                Core.flashMessage('The cadence, ' + cadence.name + ', has been deleted', 'success');

                                // remove locally
                                for (let i in $scope.cadences) {
                                    if ($scope.cadences[i].id === cadence.id) {
                                        $scope.cadences.splice(i, 1);
                                        break;
                                    }
                                }
                            },
                            function (result) {
                                delete $scope.deleting[cadence.id];
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        /**
         * Loads all InvoiceChasingCadences.
         */
        function loadCadences() {
            $scope.loading++;

            InvoiceChasingCadence.findAll(
                {
                    include: 'invoice_count',
                    paginate: 'none',
                },
                function (cadences) {
                    $scope.loading--;
                    $scope.cadences = cadences;
                },
                function (result) {
                    $scope.loading--;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
