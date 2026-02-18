/* globals moment, vex */
(function () {
    'use strict';

    angular.module('app.integrations').controller('AccountingSyncController', AccountingSyncController);

    AccountingSyncController.$inject = [
        '$scope',
        '$modal',
        '$state',
        'Core',
        'Settings',
        'Integration',
        'ReconciliationError',
        '$timeout',
        'selectedCompany',
    ];

    function AccountingSyncController(
        $scope,
        $modal,
        $state,
        Core,
        Settings,
        Integration,
        ReconciliationError,
        $timeout,
        selectedCompany,
    ) {
        $scope.reconciliationErrors = [];
        $scope.reconciliationErrorTable = {
            page: 1,
            page_count: 1,
            per_page: 10,
        };

        $scope.goToObject = goToObject;
        $scope.retry = retry;
        $scope.retryAll = retryAll;
        $scope.ignore = ignore;

        $scope.hasSyncStatus = false;
        $scope.canSyncNow = false;
        $scope.connectedToName = null;
        $scope.lastSyncedTime = null;

        // quickbooks desktop syncs
        $scope.activeJobs = [];
        $scope.pastJobs = [];
        $scope.canceled = {};

        $scope.synchronize = synchronize;
        $scope.cancel = cancelSync;
        $scope.jobDetails = jobDetails;

        $scope.goToPage = function (page, perPage) {
            $scope.reconciliationErrorTable.page = page;
            $scope.reconciliationErrorTable.per_page = perPage;
            loadReconciliationErrors(true);
        };

        $scope.$watch('integration', loadSyncActivity);

        function loadSyncActivity() {
            if ($scope.loading) {
                return;
            }

            determineStatus();

            $scope.isQuickBooksDesktop = $scope.integrationDefinition.id === 'quickbooks_desktop';
            if ($scope.isQuickBooksDesktop) {
                loadQuickBooksDesktopSyncs();
            } else {
                loadReconciliationErrors(false);
            }

            $scope.hasSyncStatus = hasSyncStatus();
            if ($scope.hasSyncStatus) {
                loadSyncStatus();
            }
        }

        function determineStatus() {
            $scope.connectedToName = $scope.integration.name || null;
            $scope.lastSyncedTime = null;

            let accountingSystem = $scope.integrationDefinition.id;
            if (accountingSystem === 'netsuite') {
                $scope.canSyncNow = false; // Syncs must be initiated through netsuite
                $scope.lastSyncedTime = false; // Last sync time is unknown
                $scope.hasSyncProfile = true;
            } else if (accountingSystem === 'quickbooks_desktop') {
                $scope.canSyncNow = false; // Syncs must be initiated through web connector
                $scope.lastSyncedTime = false; // Last sync time is unknown
            } else {
                $scope.canSyncNow = !!$scope.integration.extra.sync_profile; // Sync is only available if there is a sync profile
                $scope.hasSyncProfile = !!$scope.integration.extra.sync_profile;
                $scope.lastSyncedTime = $scope.integration.extra.sync_profile.last_synced;
            }
        }

        function loadQuickBooksDesktopSyncs() {
            Integration.getQuickBooksDesktopSyncs(
                function (syncs) {
                    $scope.activeJobs = syncs.active_jobs;
                    $scope.pastJobs = syncs.past_jobs;

                    processJobs($scope.activeJobs, true);
                    processJobs($scope.pastJobs);
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadReconciliationErrors(once) {
            ReconciliationError.findAll(
                {
                    per_page: $scope.reconciliationErrorTable.per_page,
                    page: $scope.reconciliationErrorTable.page,
                    'filter[integration]': $scope.integrationDefinition.id,
                },
                function (errors, headers) {
                    $scope.reconciliationErrors = errors;
                    $scope.totalReconciliationErrors = headers('X-Total-Count');
                    $scope.reconciliationErrorTable.page_count = Math.ceil(
                        Math.max(1, $scope.totalReconciliationErrors / $scope.reconciliationErrorTable.per_page),
                    );

                    // reload reconciliation errors every 5s
                    if (!once) {
                        reloadReconciliationErrors = $timeout(function () {
                            loadReconciliationErrors(false);
                        }, 5000);
                    }
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadSyncStatus() {
            Integration.syncStatus(
                {
                    id: $scope.integrationDefinition.id,
                },
                function (syncStatus) {
                    $scope.syncStatus = syncStatus;

                    if (syncStatus.running) {
                        $scope.canSyncNow = true;
                    }

                    $scope.syncParameters = null;
                    if (syncStatus.query) {
                        const query = angular.fromJson(syncStatus.query);
                        const parts = [];
                        const dateFormat = Core.phpDateFormatToMoment(selectedCompany.date_format);
                        const dateTimeFormat = dateFormat + ' h:mm a';
                        if (query.last_synced) {
                            parts.push('Modified since ' + moment(query.last_synced).format(dateTimeFormat));
                        }
                        if (query.end_date && query.start_date) {
                            parts.push(
                                'Date Range: ' +
                                    moment(query.start_date).format(dateFormat) +
                                    ' - ' +
                                    moment(query.end_date).format(dateFormat),
                            );
                        } else if (query.start_date) {
                            parts.push('Start Date: ' + moment(query.start_date).format(dateFormat));
                        }
                        if (query.open_items_only) {
                            parts.push('Open Items Only');
                        }
                        $scope.syncParameters = parts.join(', ');
                    }

                    if (syncStatus.finished_at) {
                        let finishedAt = moment(syncStatus.finished_at).unix();
                        if (finishedAt > $scope.lastSyncedTime) {
                            $scope.lastSyncedTime = finishedAt;
                        }
                    }

                    // reload sync status every 5s
                    reloadSyncStatus = $timeout(function () {
                        loadSyncStatus();
                    }, 5000);
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function goToObject(reconciliationError) {
            let obj = reconciliationError.object;
            $state.go('manage.' + obj + '.view.summary', { id: reconciliationError.object_id });
        }

        function retry(reconciliationError, showAlert) {
            showAlert = typeof showAlert === 'undefined' ? true : showAlert;
            if (reconciliationError.saving) {
                return;
            }

            reconciliationError.saving = true;
            ReconciliationError.retry(
                {
                    id: reconciliationError.id,
                },
                function (updated) {
                    reconciliationError.saving = false;
                    angular.extend(reconciliationError, updated);
                    if (showAlert) {
                        Core.flashMessage('This record has been queued for retry.', 'success');
                    }
                },
                function (result) {
                    reconciliationError.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function retryAll() {
            vex.dialog.confirm({
                message: 'Are you sure you want to retry all of these records?',
                callback: function (result) {
                    if (result) {
                        angular.forEach($scope.reconciliationErrors, function (reconciliationError) {
                            if (!reconciliationError.retried_at) {
                                retry(reconciliationError, false);
                            }
                        });
                        Core.flashMessage('These records have been queued for retry.', 'success');
                    }
                },
            });
        }

        function ignore(reconciliationError, $index) {
            if (reconciliationError.saving) {
                return;
            }

            reconciliationError.saving = true;
            ReconciliationError.delete(
                {
                    id: reconciliationError.id,
                },
                function () {
                    reconciliationError.saving = false;
                    $scope.reconciliationErrors.splice($index, 1);
                    $scope.totalReconciliationErrors--;
                },
                function (result) {
                    reconciliationError.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function synchronize() {
            let accountingSystem = $scope.integrationDefinition.id;
            enqueueSync(accountingSystem);
        }

        function enqueueSync(integration) {
            $scope.canSyncNow = false;
            Integration.enqueueSync(
                {
                    id: integration,
                },
                {},
                function () {
                    Core.flashMessage('Your sync has been initiated.', 'success');
                },
                function (result) {
                    $scope.canSyncNow = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function cancelSync(job) {
            $scope.canceling = true;

            Integration.cancelQuickBooksDesktopSync(
                {
                    id: job.id,
                },
                function (_job) {
                    $scope.canceling = false;
                    $scope.canceled[job.id] = true;

                    angular.extend(job, _job);
                    processJobs($scope.activeJobs, true);

                    Core.flashMessage('The kill order has been sent to your sync. It should stop shortly.', 'success');
                },
                function (result) {
                    $scope.canceling = false;

                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        let reloadQuickBooksDesktopSyncs, reloadSyncStatus, reloadReconciliationErrors;

        function processJobs(jobs, active) {
            active = active || false;

            angular.forEach(jobs, function (job) {
                // calculate percent
                job.percent = job.progress * 100;
            });

            // reload in 5s if there are any active jobs
            if (active && jobs.length > 0) {
                reloadQuickBooksDesktopSyncs = $timeout(function () {
                    loadQuickBooksDesktopSyncs();
                }, 5000);
            }
        }

        // cancel any reload promises after leaving the page
        $scope.$on('$stateChangeStart', function () {
            if (reloadQuickBooksDesktopSyncs) {
                $timeout.cancel(reloadQuickBooksDesktopSyncs);
            }
            if (reloadReconciliationErrors) {
                $timeout.cancel(reloadReconciliationErrors);
            }
            if (reloadSyncStatus) {
                $timeout.cancel(reloadSyncStatus);
            }
        });

        function jobDetails(job) {
            $modal.open({
                templateUrl: 'integrations/views/job-details.html',
                controller: 'SyncJobDetailsController',
                resolve: {
                    job: function () {
                        return job;
                    },
                },
            });
        }

        function hasSyncStatus() {
            return (
                $scope.integrationDefinition.id !== 'quickbooks_desktop' &&
                $scope.integrationDefinition.id !== 'netsuite'
            );
        }
    }
})();
