/* globals moment, UAParser */
(function () {
    'use strict';

    angular.module('app.auth').controller('AccountActivityController', AccountActivityController);

    AccountActivityController.$inject = ['$scope', '$modalInstance', '$state', 'Core', 'activity'];

    function AccountActivityController($scope, $modalInstance, $state, Core, activity) {
        $scope.activity = activity;
        $scope.tab = 'activity';

        let parser;

        angular.forEach(activity.events, function (event) {
            parseEvent(event);
        });

        angular.forEach(activity.active_sessions, function (session) {
            session.since = moment.unix(session.updated_at).fromNow();
            parseEvent(session);
        });

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.signOutAll = function () {
            $state.go('auth.logout', {
                all: true,
            });
        };

        function parseEvent(obj) {
            // parse location
            Core.lookupIp(obj.ip, function (result) {
                $scope.$apply(function () {
                    let el = [result.city, result.region, result.country];
                    obj.location = el
                        .filter(function (n) {
                            return n;
                        })
                        .join(', ');

                    // truncate IP
                    if (obj.ip.length > 15) {
                        obj.ip = obj.ip.substring(0, 15) + '...';
                    }
                });
            });

            // parse user agent
            if (!parser) {
                parser = new UAParser();
            }
            parser.setUA(obj.user_agent);
            let result = parser.getResult();
            if (result) {
                let el = [result.device.type, result.browser.name, result.os.name];
                obj.ua = el
                    .filter(function (n) {
                        return n;
                    })
                    .join('/');
            }
        }
    }
})();
