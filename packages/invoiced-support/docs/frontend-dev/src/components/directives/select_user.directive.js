(function () {
    'use strict';

    angular.module('app.catalog').directive('selectUser', selectUser);

    selectUser.$inject = ['$filter', 'InvoicedConfig'];

    function selectUser($filter, InvoicedConfig) {
        return {
            restrict: 'E',
            template:
                '<input type="hidden" ng-model="user" ui-select2="options" ng-hide="loading" ng-required="isRequired" />' +
                '<div class="loading inline" ng-show="loading"></div>',
            scope: {
                user: '=ngModel',
                watch: '=',
                isRequired: '=',
            },
            controller: [
                '$scope',
                'Member',
                'CurrentUser',
                'selectedCompany',
                function ($scope, Member, CurrentUser, selectedCompany) {
                    let escapeHtml = $filter('escapeHtml');

                    if ($scope.watch) {
                        $scope.$watch('user', $scope.watch, true);
                    }

                    $scope.options = {
                        data: {
                            results: [],
                            text: 'name',
                        },
                        initSelection: function (element, callback) {
                            let id = $(element).val();
                            if (id) {
                                let jqxhr = $.ajax({
                                    url: InvoicedConfig.apiBaseUrl + '/members?filter[user_id]=' + id,
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
                                        if (result.length === 1) {
                                            let user = result[0].user;
                                            if (user.id == CurrentUser.profile.id) {
                                                user.name = 'Me';
                                            } else {
                                                user.name = user.first_name + ' ' + user.last_name;
                                            }
                                            callback(user);
                                        } else {
                                            callback(null);
                                        }
                                    })
                                    .fail(function () {
                                        callback(null);
                                    });
                            }
                        },
                        formatSelection: function (user) {
                            return escapeHtml(user.name);
                        },
                        formatResult: function (user) {
                            return "<div class='title'>" + escapeHtml(user.name) + '</div>';
                        },
                        placeholder: 'Select a user',
                        width: '100%',
                    };

                    // Load all users
                    Member.all(function (members) {
                        let myUser = angular.copy(CurrentUser.profile);
                        myUser.text = 'Me';
                        myUser.name = 'Me';
                        let data = [myUser];
                        angular.forEach(members, function (member) {
                            if (member.user.id != CurrentUser.profile.id) {
                                let _user = angular.copy(member.user);
                                // the text is the searchable part
                                _user.text = _user.first_name + ' ' + _user.last_name;
                                _user.name = _user.text;
                                data.push(_user);
                            }
                        });

                        $scope.options.data.results = data;
                    });
                },
            ],
        };
    }
})();
