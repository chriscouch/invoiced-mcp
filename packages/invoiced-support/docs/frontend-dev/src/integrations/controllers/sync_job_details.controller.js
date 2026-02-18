/* global moment, vex */
(function () {
    'use strict';

    angular.module('app.integrations').controller('SyncJobDetailsController', SyncJobDetailsController);

    SyncJobDetailsController.$inject = [
        '$scope',
        '$modalInstance',
        '$location',
        'Integration',
        'job',
        'ObjectDeepLink',
    ];

    function SyncJobDetailsController($scope, $modalInstance, $location, Integration, job, ObjectDeepLink) {
        $scope.job = job;
        $scope.succeededRecords = [];
        $scope.failedRecords = [];
        let skipped = {};

        let delta = moment.unix(job.created_at).diff(moment.unix(job.updated_at));
        $scope.duration = moment.duration(delta).humanize();

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.skipRecord = function (record) {
            vex.dialog.confirm({
                message: 'Do you want future syncs to skip this record? This action cannot be undone.',
                callback: function (result) {
                    if (result) {
                        skipRecord(record);
                    }
                },
            });
        };

        $scope.isSkipped = function (record) {
            let k = record.object + record.object_id;
            return typeof skipped[k] !== 'undefined';
        };

        loadSyncedRecords();

        function loadSyncedRecords() {
            $scope.loading = true;
            Integration.syncedQuickBooksDesktopRecords(
                {
                    id: job.id,
                },
                function (records) {
                    $scope.loading = false;
                    angular.forEach(records, function (record) {
                        record.link = recordLink(record);
                        if (record.succeeded) {
                            $scope.succeededRecords.push(record);
                        } else {
                            $scope.failedRecords.push(record);
                        }
                    });
                },
                function (result) {
                    $scope.loading = false;
                    $scope.error = result.data;
                },
            );
        }

        function skipRecord(record) {
            $scope.skipping = true;
            $scope.skipError = false;
            Integration.skipQuickBooksDesktopRecord(
                {},
                {
                    type: $scope.job.type,
                    object: record.object,
                    id: record.object_id,
                },
                function () {
                    $scope.skipping = false;
                    skipped[record.object + record.object_id] = true;
                },
                function (result) {
                    $scope.skipping = false;
                    $scope.skipError = result.data;
                },
            );
        }

        function recordLink(record) {
            return ObjectDeepLink.getUrl(record.object, record.object_id);
        }
    }
})();
