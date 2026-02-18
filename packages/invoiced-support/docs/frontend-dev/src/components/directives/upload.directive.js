(function () {
    'use strict';

    angular.module('app.components').directive('upload', upload);

    upload.$inject = ['uploadManager', 'selectedCompany'];

    function upload(uploadManager, selectedCompany) {
        return {
            restrict: 'A',
            scope: {
                ngUrl: '=?',
            },
            link: function (scope, element) {
                $(element).fileupload({
                    sequentialUploads: true,
                    type: 'POST',
                    dataType: 'json',
                    url: scope.ngUrl,
                    headers: {
                        Authorization: selectedCompany.auth_header,
                    },
                    xhrFields: {
                        withCredentials: false,
                    },
                    add: function (e, data) {
                        uploadManager.add(data);
                    },
                    progressall: function (e, data) {
                        let progress = parseInt((data.loaded / data.total) * 100, 10);
                        uploadManager.setProgress(progress);
                    },
                    done: function (e, data) {
                        uploadManager.setProgress(0);
                        uploadManager.done(data);
                    },
                });

                scope.$watch('ngUrl', function (url) {
                    $(element).fileupload('option', 'url', url);
                });
            },
        };
    }
})();
