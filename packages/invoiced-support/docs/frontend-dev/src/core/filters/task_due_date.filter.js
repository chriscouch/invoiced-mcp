/* globals moment */
(function () {
    'use strict';

    angular.module('app.core').filter('taskDueDate', taskDueDate);

    taskDueDate.$inject = ['Core', 'selectedCompany'];

    function taskDueDate(Core, selectedCompany) {
        let now = moment();
        let yesterday = moment().subtract(1, 'days');
        let tomorrow = moment().add(1, 'days');

        return function (dueDate) {
            dueDate = moment.unix(dueDate);
            if (dueDate.isSame(now, 'day')) {
                return 'Today';
            } else if (dueDate.isSame(yesterday, 'day')) {
                return 'Yesterday';
            } else if (dueDate.isSame(tomorrow, 'day')) {
                return 'Tomorrow';
            }

            return dueDate.format(Core.phpDateFormatToMoment(selectedCompany.date_format));
        };
    }
})();
