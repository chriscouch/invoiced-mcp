(function () {
    'use strict';

    angular.module('app.catalog').directive('selectGlAccount', selectGlAccount);

    selectGlAccount.$inject = ['$filter', 'InvoicedConfig'];

    function selectGlAccount($filter, InvoicedConfig) {
        let escapeHtml = $filter('escapeHtml');

        return {
            restrict: 'E',
            template:
                '<input type="hidden" ng-model="glAccount" ui-select2="options" ng-hide="loading" required />' +
                '<div class="loading inline" ng-show="loading"></div>',
            scope: {
                glAccount: '=ngModel',
            },
            controller: [
                '$scope',
                '$filter',
                '$modal',
                'GlAccount',
                'Core',
                'selectedCompany',
                function ($scope, $filter, $modal, GlAccount, Core, selectedCompany) {
                    $scope.options = {
                        data: {
                            results: [],
                            text: 'name',
                        },
                        initSelection: function (element, callback) {
                            let id = $(element).val();
                            if (id) {
                                let jqxhr = $.ajax({
                                    url: InvoicedConfig.apiBaseUrl + '/gl_accounts/' + id,
                                    method: 'GET',
                                    dataType: 'json',
                                    headers: {
                                        Authorization: selectedCompany.auth_header,
                                    },
                                    xhrFields: {
                                        withCredentials: false,
                                    },
                                });

                                jqxhr
                                    .done(function (result) {
                                        callback(result);
                                    })
                                    .fail(function () {
                                        callback(null);
                                    });
                            }
                        },
                        formatSelection: function (glAccount) {
                            return escapeHtml(glAccount.name);
                        },
                        formatResult: function (glAccount) {
                            return (
                                "<div class='title'>" +
                                glAccount._name +
                                '</div>' +
                                "<div class='details'>" +
                                glAccount._code +
                                '</div>'
                            );
                        },
                        placeholder: 'Select an account',
                        width: '100%',
                    };

                    // Load all accounts
                    loadAccounts();

                    function loadAccounts() {
                        $scope.loading = true;
                        GlAccount.all(
                            function (glAccounts) {
                                $scope.loading = false;

                                let data = [];
                                angular.forEach(glAccounts, function (glAccount) {
                                    let _glAccount = angular.copy(glAccount);

                                    // add indentation based on level
                                    _glAccount._name = '';
                                    _glAccount._code = '';
                                    for (let i = 0; i < glAccount.level; i++) {
                                        _glAccount._name += '&nbsp;&nbsp;';
                                        _glAccount._code += '&nbsp;&nbsp;';
                                    }
                                    _glAccount._name += escapeHtml(glAccount.name);
                                    _glAccount._code += escapeHtml(glAccount.code);

                                    // the text is the searchable part
                                    _glAccount.text = glAccount.name + ' ' + glAccount.code;
                                    data.push(_glAccount);
                                });

                                $scope.options.data.results = data;
                            },
                            function (message) {
                                $scope.loading = false;
                                Core.flashMessage(message, 'error');
                            },
                        );
                    }
                },
            ],
        };
    }
})();
