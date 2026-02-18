(function () {
    'use strict';

    angular.module('app.components').directive('languageSelector', languageSelector);

    function languageSelector() {
        let languages;

        return {
            restrict: 'E',
            template:
                '<div class="invoiced-select">' +
                '<select ng-model="model" name="language" ng-options="l.code as l.language group by l.group for l in languages" ng-change="change(model)" ng-required="required"></select>' +
                '</div>',
            scope: {
                model: '=ngModel',
                hasDefault: '=hasDefault',
                required: '=isRequired',
                callback: '&?ngChange',
            },
            controller: [
                'InvoicedConfig',
                '$scope',
                function (InvoicedConfig, $scope) {
                    if (!languages) {
                        languages = angular
                            .copy(InvoicedConfig.languages)
                            .sort((a, b) => a.language.localeCompare(b.language));
                        angular.forEach(languages, function (language) {
                            if (language.translated) {
                                language.group = 'Supported';
                            } else {
                                language.group = 'Other';
                            }
                        });

                        if ($scope.hasDefault || typeof $scope.hasDefault === 'undefined') {
                            languages.splice(0, 0, {
                                code: '',
                                language: '- Default Language',
                                group: 'Supported',
                            });
                        }
                    }

                    $scope.languages = languages;

                    $scope.change = function (language) {
                        if (typeof $scope.callback === 'function') {
                            $scope.callback({
                                language: language,
                            });
                        }
                    };
                },
            ],
        };
    }
})();
