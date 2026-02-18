(function () {
    'use strict';

    angular.module('app.inboxes').controller('ViewEmailThreadController', ViewEmailThreadController);

    ViewEmailThreadController.$inject = [
        '$rootScope',
        '$scope',
        'EmailThread',
        '$stateParams',
        'Core',
        'BrowsingHistory',
    ];

    function ViewEmailThreadController($rootScope, $scope, EmailThread, $stateParams, Core, BrowsingHistory) {
        $scope.thread = null;
        $scope.inboxId = $stateParams.id;
        $scope.status = $stateParams.status;

        $('html').addClass('email-thread-open');
        $rootScope.$on('$stateChangeSuccess', function () {
            $('html').removeClass('email-thread-open');
        });

        $scope.$on('refreshInbox', loadThread);
        loadThread();

        function loadThread() {
            // Only show loading indicator on initial load
            if (!$scope.thread) {
                $scope.loading = true;
            }

            EmailThread.get(
                {
                    id: $stateParams.threadId,
                    expand: 'customer,assignee',
                },
                function (result) {
                    $scope.thread = result;
                    $scope.loading = false;
                    Core.setTitle(result.name);

                    BrowsingHistory.push({
                        id: $stateParams.threadId,
                        inboxId: $stateParams.id,
                        status: $stateParams.status,
                        type: 'email',
                        title: result.name,
                    });

                    $scope.$broadcast('refreshEmailThread', $scope.thread);
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
