(function () {
    'use strict';

    angular.module('app.files').factory('File', File);

    File.$inject = ['$resource', 'InvoicedConfig'];

    function File($resource, InvoicedConfig) {
        let file = $resource(
            InvoicedConfig.apiBaseUrl + '/files/:id',
            {
                id: '@id',
            },
            {
                post: {
                    method: 'POST',
                },
                delete: {
                    method: 'DELETE',
                },
            },
        );

        file.create = function (_file) {
            let url = InvoicedConfig.fileUploadUrl + encodeURIComponent(_file.key);
            return file.post({
                name: _file.filename,
                type: _file.mimetype,
                size: _file.size,
                url: url,
            }).$promise;
        };

        return file;
    }
})();
