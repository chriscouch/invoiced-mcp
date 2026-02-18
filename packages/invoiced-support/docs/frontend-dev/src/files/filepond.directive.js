(function () {
    'use strict';

    angular.module('app.files').directive('filepond', filepond);

    filepond.inject = ['$window', 'InvoicedConfig', 'selectedCompany'];

    function filepond($window, InvoicedConfig, selectedCompany) {
        return {
            restrict: 'E',
            template: '<file-pond ' + 'config="filePondConfig" ' + 'on-init="initialize" ' + '></file-pond>',
            scope: {
                callback: '=',
                allowMultiple: '=?',
                dropOnPage: '=?',
                types: '=?',
                oninitfile: '=?',
            },
            link: function ($scope) {
                $scope.allowMultiple = typeof $scope.allowMultiple === 'undefined' ? true : !!$scope.allowMultiple;
                if (!$scope.types) {
                    $scope.types = [
                        // Images
                        'image/jpeg',
                        'image/png',
                        'image/jpg',
                        'image/gif',
                        // MS Office
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        // iWork
                        'application/vnd.apple.pages',
                        'application/vnd.apple.numbers',
                        'application/vnd.apple.keynote',
                        // Misc
                        'text/plain',
                        'application/rtf',
                        'application/pdf',
                        'application/zip',
                        'text/csv',
                    ];
                }

                $scope.filePondConfig = {
                    name: 'file',
                    allowMultiple: !!$scope.allowMultiple,
                    server: {
                        url: '/api/files',
                        process: {
                            withCredentials: false,
                            headers: {
                                Authorization: 'Basic ' + $window.btoa(selectedCompany.dashboard_api_key + ':'),
                                'X-App-Version': InvoicedConfig.version,
                            },
                            onload: response => {
                                $scope.callback(JSON.parse(response));
                            },
                            ondata: data => {
                                angular.forEach(data.getAll('file'), file => {
                                    if (file.name) {
                                        data.set('filename', file.name.replaceAll('+', '_'));

                                        return false;
                                    }
                                });

                                return data;
                            },
                        },
                        revert: () => {},
                    },
                    credits: ['', ''],
                    dropOnPage: $scope.dropOnPage ? $scope.dropOnPage : true,
                    maxFileSize: '20MB',
                    acceptedFileTypes: $scope.types,
                    oninitfile: $scope.oninitfile,
                };

                $scope.initialize = () => {};
            },
        };
    }
})();
