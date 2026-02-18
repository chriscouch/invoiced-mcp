(function () {
    'use strict';

    angular.module('app.inboxes').controller('DocumentMessagesController', DocumentMessagesController);

    DocumentMessagesController.$inject = ['$scope', 'EmailThread', '$stateParams', 'Core', 'Settings', 'type'];

    function DocumentMessagesController($scope, EmailThread, $stateParams, Core, Settings, type) {
        $scope.loading = true;
        $scope.thread = null;
        let id = $stateParams.id;

        let includesTextsAndLetters = 'invoice' === type;
        $scope.threadOptions = {
            documentType: type,
            documentId: id,
            includeTexts: includesTextsAndLetters,
            includeLetters: includesTextsAndLetters,
        };

        // Determine if A/R or A/P inbox is needed
        let settingsFn = Settings.accountsReceivable;
        if (type === 'bill' || type === 'vendor_credit') {
            settingsFn = Settings.accountsPayable;
        }

        settingsFn(function (settings) {
            $scope.thread = null;
            EmailThread.relatedThread(
                {
                    documentType: type,
                    documentId: id,
                },
                function (thread) {
                    $scope.loading = false;

                    if (thread.id) {
                        $scope.thread = thread;
                        $scope.$broadcast('refreshEmailThread', $scope.thread);
                    } else {
                        // this endpoint can return an empty object if the thread does not exist
                        // and so we must create the template for a new thread
                        $scope.thread = {
                            inbox_id: settings.inbox,
                            status: 'closed',
                            object_type: type,
                            related_to_id: id,
                        };

                        if (includesTextsAndLetters) {
                            // refresh the thread despite no thread existing
                            // to load the text messages and letters
                            $scope.$broadcast('refreshEmailThread', $scope.thread);
                        }
                    }
                },
                errorHandler,
            );
        }, errorHandler);

        function errorHandler(result) {
            $scope.loading = false;
            Core.showMessage(result.data.message, 'error');
        }
    }
})();
