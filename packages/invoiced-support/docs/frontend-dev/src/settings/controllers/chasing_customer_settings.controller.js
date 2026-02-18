/* globals moment, vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('ChasingCustomerSettingsController', ChasingCustomerSettingsController);

    ChasingCustomerSettingsController.$inject = [
        '$scope',
        '$modal',
        'Company',
        'selectedCompany',
        'Core',
        'Settings',
        'ChasingCadence',
        'LeavePageWarning',
        'Feature',
    ];

    function ChasingCustomerSettingsController(
        $scope,
        $modal,
        Company,
        selectedCompany,
        Core,
        Settings,
        ChasingCadence,
        LeavePageWarning,
        Feature,
    ) {
        $scope.hasFeature = Feature.hasFeature('smart_chasing');
        $scope.cadences = [];
        $scope.loading = 0;
        $scope.deleting = {};

        $scope.run = function (cadence) {
            $scope.error = null;
            vex.dialog.confirm({
                message:
                    'Are you sure you want to run this cadence now? It is already scheduled to run automatically ' +
                    cadence.next_run,
                callback: function (result) {
                    if (result) {
                        ChasingCadence.run(
                            {
                                id: cadence.id,
                            },
                            {},
                            function () {
                                Core.flashMessage(
                                    'The cadence, ' + cadence.name + ', has been queued up and will begin shortly.',
                                    'success',
                                );
                                cadence.last_run = moment().unix();
                                parseCadence(cadence);
                            },
                            function (result) {
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        $scope.assign = function (cadence) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'collections/views/mass-assign-chasing-cadence.html',
                controller: 'MassAssignChasingCadenceController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                resolve: {
                    cadence: function () {
                        return cadence;
                    },
                },
            });

            modalInstance.result.then(
                function (n) {
                    LeavePageWarning.unblock();
                    cadence.num_customers += n;

                    Core.flashMessage(
                        n +
                            ' customer' +
                            (n != 1 ? 's' : '') +
                            ' have been enrolled in the ' +
                            cadence.name +
                            ' cadence.',
                        'success',
                    );
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.setPaused = function (cadence, paused) {
            $scope.deleting[cadence.id] = true;
            $scope.error = null;

            ChasingCadence.edit(
                {
                    id: cadence.id,
                },
                {
                    paused: paused,
                },
                function (_cadence) {
                    angular.extend(cadence, _cadence);
                    parseCadence(cadence);
                    delete $scope.deleting[cadence.id];

                    Core.flashMessage('The cadence, ' + cadence.name + ', has been updated', 'success');
                },
                function (result) {
                    delete $scope.deleting[cadence.id];
                    $scope.error = result.data;
                },
            );
        };

        $scope.delete = function (cadence) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this cadence?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[cadence.id] = true;
                        $scope.error = null;

                        ChasingCadence.delete(
                            {
                                id: cadence.id,
                            },
                            function () {
                                delete $scope.deleting[cadence.id];

                                Core.flashMessage('The cadence, ' + cadence.name + ', has been deleted', 'success');

                                // remove locally
                                for (let i in $scope.cadences) {
                                    if ($scope.cadences[i].id == cadence.id) {
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

        Core.setTitle('Customer Chasing');
        loadCadences();
        loadSettings();

        function loadCadences() {
            $scope.loading++;

            ChasingCadence.findAll(
                {
                    include: 'num_customers',
                    paginate: 'none',
                },
                function (cadences) {
                    $scope.loading--;
                    angular.forEach(cadences, parseCadence);
                    $scope.cadences = cadences;
                },
                function (result) {
                    $scope.loading--;
                    $scope.error = result.data;
                },
            );
        }

        function parseCadence(cadence) {
            if (cadence.last_run > 0) {
                cadence.last_run = moment.unix(cadence.last_run).calendar();
            }

            if (cadence.next_run > 0) {
                cadence.next_run = moment.unix(cadence.next_run).calendar();
            }
        }

        function loadSettings() {
            $scope.loading++;

            Settings.accountsReceivable(
                function (settings) {
                    $scope.loading--;
                    $scope.settings = settings;
                },
                function (result) {
                    $scope.loading--;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
