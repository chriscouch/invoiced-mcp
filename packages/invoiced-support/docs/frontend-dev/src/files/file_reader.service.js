(function () {
    'use strict';

    angular.module('app.files').factory('FileReader', FileReaderService);

    function FileReaderService() {
        let _FileReaderService = {};

        _FileReaderService.read = function (element, success, failure) {
            let reader = new FileReader(),
                files = element.files;

            let file = files[0];

            reader.onload = function (e) {
                e = e || window.event; // get window.event if e argument missing (in IE)

                // 20MB file size limit
                if (file.size > 209592320) {
                    failure('The file you have selected is too large. It must be 20MB or less');
                    return;
                }

                success(e.target.result);
            };

            reader.onerror = function (e) {
                e = e || window.event; // get window.event if e argument missing (in IE)

                switch (e.target.error.code) {
                    case e.target.error.NOT_FOUND_ERR:
                        failure('File not found!');
                        break;

                    case e.target.error.NOT_READABLE_ERR:
                        failure('File not readable!');
                        break;

                    case e.target.error.ABORT_ERR:
                        failure('Read operation was aborted!');
                        break;

                    case e.target.error.SECURITY_ERR:
                        failure('File is in a locked state!');
                        break;

                    case e.target.error.ENCODING_ERR:
                        failure('The file is too long to encode in a "data://" URL.');
                        break;

                    default:
                        failure('Read error.');
                }
            };

            reader.readAsDataURL(file);
        };

        return _FileReaderService;
    }
})();
