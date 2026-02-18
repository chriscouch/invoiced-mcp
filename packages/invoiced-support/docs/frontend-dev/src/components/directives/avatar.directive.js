(function () {
    'use strict';

    angular.module('app.components').directive('avatar', avatar);

    avatar.$inject = ['Core'];

    function avatar(Core) {
        return {
            restrict: 'A',
            scope: {
                opts: '=avatar',
                user: '=',
            },
            link: function (scope, element) {
                scope.$watch('user', buildInitials, true);
                scope.$watch('opts', buildInitials, true);

                function buildInitials() {
                    element.initial(getOptions());
                }

                function getOptions() {
                    let name = '';
                    if (typeof scope.user == 'object') {
                        if (typeof scope.user.name === 'undefined' && typeof scope.user.first_name !== 'undefined') {
                            scope.user.name = scope.user.first_name + ' ' + scope.user.last_name;
                        }

                        name = scope.user.name;
                    } else if (typeof scope.user == 'string') {
                        name = scope.user;
                    }

                    let opts = scope.opts || {};

                    return angular.extend(
                        {
                            name: Core.generateInitials(name),
                            charCount: 2,
                            fontSize: 14,
                            height: 35,
                            width: 35,
                        },
                        opts,
                    );
                }
            },
        };
    }
})();
