(function () {
    'use strict';

    angular.module('app.core').factory('DatePickerService', DatePickerService);

    DatePickerService.$inject = ['Core', 'selectedCompany'];

    function DatePickerService(Core, selectedCompany) {
        return {
            // pushes a blocker on to the stack
            getOptions: function (options) {
                if (options === undefined) {
                    options = {};
                }
                return angular.extend(
                    {
                        beforeShow: function (input, inst) {
                            inst.dpDiv.addClass('notranslate');
                        },
                        dateFormat: Core.phpDateFormatToDatepicker(selectedCompany.date_format),
                        showButtonPanel: true,
                        changeMonth: true,
                        changeYear: true,
                    },
                    options,
                );
            },
        };
    }
})();
