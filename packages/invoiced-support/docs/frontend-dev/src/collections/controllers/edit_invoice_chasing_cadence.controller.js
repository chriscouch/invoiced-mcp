(function () {
    'use strict';

    angular
        .module('app.collections')
        .controller('EditInvoiceChasingCadenceController', EditInvoiceChasingCadenceController);

    EditInvoiceChasingCadenceController.$inject = [
        '$scope',
        '$state',
        '$stateParams',
        'Company',
        'LeavePageWarning',
        'selectedCompany',
        'Core',
        'InvoiceChasingCadence',
        'Feature',
    ];

    function EditInvoiceChasingCadenceController(
        $scope,
        $state,
        $stateParams,
        Company,
        LeavePageWarning,
        selectedCompany,
        Core,
        InvoiceChasingCadence,
        Feature,
    ) {
        /**
         * Initialization
         */

        $scope.hasFeature = Feature.hasFeature('smart_chasing') && Feature.hasFeature('invoice_chasing');
        $scope.chaseSchedule = [];
        $scope.saving = false;
        $scope.loading = 0;

        LeavePageWarning.watchForm($scope, 'invoiceCadenceForm');

        if ($stateParams.id) {
            Core.setTitle('Edit Chasing Cadence');
            loadCadence($stateParams.id);
            $scope.isExisting = true;
        } else {
            Core.setTitle('New Chasing Cadence');
            $scope.cadence = {
                name: '',
                chase_schedule: [],
            };
        }

        // InvoiceCadenceEditor callback.
        //
        // NOTE: This bulk of the form which this controller handles
        // is managed by the InvoiceCadenceEditor directive. The directive
        // manages the manipulation of the invoice chasing schedule and
        // passes back modifications to this controller via a change
        // handler provided to the directive. This function is the
        // change handler.
        $scope.onChaseScheduleChange = function (newSchedule) {
            $scope.chaseSchedule = newSchedule;
        };

        /**
         * Saves the InvoiceChasingCadence.
         */
        $scope.save = function (cadence) {
            $scope.saving = true;
            $scope.error = null;

            if (cadence.id) {
                edit(cadence);
            } else {
                create(cadence);
            }
        };

        /**
         * Creates a new InvoiceChasingCadence
         */
        function create(cadence) {
            InvoiceChasingCadence.create(
                {
                    name: cadence.name,
                    chase_schedule: formatChaseSchedule($scope.chaseSchedule),
                },
                function () {
                    $scope.saving = false;
                    Core.flashMessage('Your chasing cadence has been created.', 'success');

                    LeavePageWarning.unblock();
                    $state.go('manage.settings.chasing.invoices');
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        /**
         * Edits an existing InvoiceChasingCadence
         */
        function edit(cadence) {
            InvoiceChasingCadence.edit(
                {
                    id: cadence.id,
                },
                {
                    name: cadence.name,
                    chase_schedule: formatChaseSchedule($scope.chaseSchedule),
                },
                function () {
                    $scope.saving = false;
                    Core.flashMessage('Your chasing cadence has been updated.', 'success');

                    LeavePageWarning.unblock();
                    $state.go('manage.settings.chasing.invoices');
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        /**
         * Loads InvoiceChasingCadence by id from Invoiced.
         */
        function loadCadence(id) {
            $scope.loading++;

            InvoiceChasingCadence.find(
                {
                    id: id,
                },
                function (cadence) {
                    $scope.loading--;
                    $scope.cadence = cadence;
                    $scope.chaseSchedule = angular.copy(cadence.chase_schedule);

                    if ($state.current.name === 'manage.collections.duplicate_invoice_chasing_cadence') {
                        // delete ids for duplication
                        delete cadence.id;
                        angular.forEach(cadence.steps, function (step) {
                            delete step.id;
                        });

                        Core.setTitle('New Chasing Cadence');
                        $scope.canEditSteps = true;
                        $scope.isExisting = false;
                    } else {
                        $scope.canEditSteps = true;
                    }
                },
                function (result) {
                    $scope.loading--;
                    $scope.error = result.data;
                },
            );
        }

        /**
         * Formats chase schedule for edit request by converting
         * numeric strings to integer values.
         */
        function formatChaseSchedule(chaseSchedule) {
            return chaseSchedule.map(function (step) {
                let options = {};
                // convert numeric string values to integers
                angular.forEach(Object.keys(step.options), function (key) {
                    let value = step.options[key];
                    if (typeof value === 'string' && !isNaN(value)) {
                        options[key] = parseInt(value);
                    } else {
                        options[key] = value;
                    }
                });

                return {
                    id: step.id,
                    trigger: parseInt(step.trigger),
                    options: options,
                };
            });
        }
    }
})();
