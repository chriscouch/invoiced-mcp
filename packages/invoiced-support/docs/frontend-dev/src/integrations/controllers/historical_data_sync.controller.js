/* globals moment, vex */
(function () {
    'use strict';

    angular.module('app.integrations').controller('InitialDataSyncController', InitialDataSyncController);

    InitialDataSyncController.$inject = ['$scope', '$state', 'Core', 'Integration'];

    function InitialDataSyncController($scope, $state, Core, Integration) {
        //
        // Methods
        //

        $scope.settings = {
            period: {
                start: moment().subtract(1, 'months').toDate(),
                end: moment().toDate(),
                period: ['months', 1],
            },
            open_items_only: false,
        };

        $scope.readers = [];
        if (typeof $scope.integrationDefinition.initialDataSync !== 'undefined') {
            angular.forEach($scope.integrationDefinition.initialDataSync, function (reader) {
                reader = angular.copy(reader);
                reader.enabled = false;
                $scope.readers.push(reader);
            });
        }

        $scope.start = function (settings, readers) {
            vex.dialog.confirm({
                message: 'Are you sure you want to perform this sync?',
                callback: function (result) {
                    if (result) {
                        start(settings, readers);
                    }
                },
            });
        };

        //
        // Initialization
        //

        Core.setTitle('Initial Data Sync');

        function start(settings, readers) {
            $scope.starting = true;

            const enabledReaders = [];
            angular.forEach(readers, function (reader) {
                if (reader.enabled) {
                    enabledReaders.push(reader.id);
                }
            });

            Integration.enqueueSync(
                {
                    id: $scope.integrationDefinition.id,
                },
                {
                    historical_sync: true,
                    start_date: moment(settings.period.start).format('YYYY-MM-DD'),
                    end_date: moment(settings.period.end).format('YYYY-MM-DD'),
                    open_items_only: settings.open_items_only,
                    readers: enabledReaders,
                },
                function () {
                    $scope.starting = false;
                    Core.flashMessage('Your sync has been queued and will begin shortly.', 'success');
                    $state.go('manage.settings.app.accounting_sync', { id: $scope.integrationDefinition.id });
                },
                function (result) {
                    $scope.starting = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
