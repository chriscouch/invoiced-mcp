/* globals inflection */
(function () {
    'use strict';

    angular.module('app.exports').controller('ExportController', ExportController);

    ExportController.$inject = [
        '$scope',
        '$controller',
        '$timeout',
        '$window',
        '$modalInstance',
        'LeavePageWarning',
        'Export',
        'type',
        'options',
    ];

    function ExportController(
        $scope,
        $controller,
        $timeout,
        $window,
        $modalInstance,
        LeavePageWarning,
        Export,
        type,
        options,
    ) {
        $scope.emailWhenReady = emailWhenReady;
        $scope.cancel = cancel;
        $scope.progress = 0;
        $scope.type = inflection.pluralize(type);

        $scope.close = function () {
            LeavePageWarning.unblock();
            $modalInstance.dismiss('cancel');

            if (reload) {
                $timeout.cancel(reload);
            }
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        let exportStarted;
        let reload;
        let refreshAfter = 0;
        let refreshStep = 1000; // 1s
        let maxRefreshAfter = 3000; // 3s
        let canEmailAfter = 10000; // 10s

        start(type, options);

        function start(type, options) {
            LeavePageWarning.block();
            $scope.exporting = true;
            exportStarted = new Date();

            Export.create(
                {
                    type: type,
                    options: options,
                },
                postFind,
                function (result) {
                    $scope.exporting = false;
                    $scope.error = result.data.message;
                    LeavePageWarning.unblock();
                },
            );
        }

        function load(id) {
            Export.find(
                {
                    id: id,
                },
                postFind,
                function (result) {
                    $scope.exporting = false;
                    $scope.error = result.data.message;
                    LeavePageWarning.unblock();
                },
            );
        }

        function postFind(_export) {
            $scope.export = _export;

            if (_export.status == 'pending') {
                // calculate progress
                $scope.progress = 0;
                if (_export.position > 0 && _export.total_records > 0) {
                    $scope.progress = Math.min(100, Math.round((_export.position / _export.total_records) * 100));
                }

                // reload in 1s, 2s, 3s
                // (repeating) if the export has not finished yet
                refreshAfter = Math.min(maxRefreshAfter, refreshAfter + refreshStep);

                reload = $timeout(function () {
                    if (new Date().getTime() - exportStarted.getTime() > canEmailAfter) {
                        $scope.$apply(function () {
                            $scope.canEmail = true;
                        });
                    }

                    load(_export.id);
                }, refreshAfter);
            } else if (_export.status == 'succeeded') {
                LeavePageWarning.unblock();
                $scope.exporting = false;
                $scope.downloaded = true;
                $scope.downloadLink = _export.download_url;
                $window.open(_export.download_url);
            } else if (_export.status === 'failed') {
                LeavePageWarning.unblock();
                $scope.exporting = false;
                $scope.error = 'Sorry, it looks like your export failed to build.';
            }
        }

        function emailWhenReady() {
            $scope.close();
        }

        function cancel(_export) {
            Export.cancel(
                {
                    id: _export.id,
                },
                function () {
                    $scope.close();
                },
                function () {
                    $scope.close();
                },
            );
        }
    }
})();
