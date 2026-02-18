/* globals moment */
(function () {
    'use strict';

    angular.module('app.auth').controller('AccountController', AccountController);

    AccountController.$inject = ['$scope', '$state', '$modal', '$timeout', '$window', 'CurrentUser', 'CSRF', 'Core'];

    function AccountController($scope, $state, $modal, $timeout, $window, CurrentUser, CSRF, Core) {
        //
        // Models
        //

        let user = CurrentUser.profile;
        $scope.user = angular.copy(user);
        $scope.companies = CurrentUser.companies;
        $scope.newEmail = user.email;
        $scope.firstName = user.first_name;
        $scope.lastName = user.last_name;
        $scope.defaultCompany = $scope.companies[0];
        angular.forEach($scope.companies, function (company) {
            if (company.id == user.default_company_id) {
                $scope.defaultCompany = company;
            }
        });
        $scope.defaultCompanyId = $scope.defaultCompany.id;
        $scope.twoFactorEnabled = user.two_factor_enabled;
        $scope.fromSSO = CurrentUser.from_sso;

        //
        // Methods
        //

        $scope.updateEmail = function (password, email) {
            $scope.savingEmail = true;
            $scope.emailError = null;

            // retrieve a fresh CSRF token first
            CSRF(function () {
                CurrentUser.edit(
                    {
                        current_password: password,
                        email: email,
                    },
                    function (user) {
                        $scope.savingEmail = false;
                        $scope.editEmail = false;

                        Core.flashMessage('Your email address has been updated to ' + email + '.', 'success');

                        angular.extend($scope.user, user);
                        $scope.current_password = '';
                    },
                    function (result) {
                        $scope.savingEmail = false;
                        $scope.emailError = result.data;
                    },
                );
            });
        };

        $scope.updatePassword = function (current, password1, password2) {
            $scope.savingPassword = true;
            $scope.passwordError = null;

            // retrieve a fresh CSRF token first
            CSRF(function () {
                CurrentUser.edit(
                    {
                        current_password: current,
                        password: [password1, password2],
                    },
                    function (user) {
                        $scope.savingPassword = false;
                        $scope.editPassword = false;

                        Core.flashMessage('Your password has been updated.', 'success');

                        $state.go('index');
                        $timeout(() => {
                            $window.location.reload();
                        });

                        angular.extend($scope.user, user);
                        $scope.current_password = '';
                        $scope.password1 = '';
                        $scope.password2 = '';
                    },
                    function (result) {
                        $scope.savingPassword = false;
                        $scope.passwordError = result.data;
                    },
                );
            });
        };

        $scope.enable2fa = function () {
            const modalInstance = $modal.open({
                templateUrl: 'auth/views/setup-2fa.html',
                controller: 'Setup2FAController',
            });

            modalInstance.result.then(function (enabled) {
                $scope.twoFactorEnabled = enabled;
            });
        };

        $scope.disable2fa = function () {
            const modalInstance = $modal.open({
                templateUrl: 'auth/views/remove-2fa.html',
                controller: 'Remove2FAController',
            });

            modalInstance.result.then(function (enabled) {
                $scope.twoFactorEnabled = enabled;
            });
        };

        $scope.updateProfile = function (firstName, lastName, defaultCompany) {
            $scope.savingProfile = true;
            $scope.profileError = null;

            // retrieve a fresh CSRF token first
            CSRF(function () {
                CurrentUser.edit(
                    {
                        first_name: firstName,
                        last_name: lastName,
                        default_company_id: defaultCompany,
                    },
                    function (user) {
                        $scope.savingProfile = false;
                        $scope.editProfile = false;

                        Core.flashMessage('Your account has been updated.', 'success');

                        $scope.user.name = firstName + ' ' + lastName;
                        angular.extend($scope.user, user);
                        angular.forEach($scope.companies, function (company) {
                            if (company.id == defaultCompany) {
                                $scope.defaultCompany = company;
                            }
                        });
                    },
                    function (result) {
                        $scope.savingProfile = false;
                        $scope.profileError = result.data;
                    },
                );
            });
        };

        $scope.accountActivity = function (activity) {
            $modal.open({
                templateUrl: 'auth/views/account-activity.html',
                controller: 'AccountActivityController',
                resolve: {
                    activity: function () {
                        return activity;
                    },
                },
                size: 'lg',
            });
        };

        //
        // Initialization
        //

        Core.setTitle('My Account');
        loadActivity();

        function loadActivity() {
            CurrentUser.activity(
                function (activity) {
                    $scope.activity = activity;

                    if (activity.events.length > 0) {
                        $scope.lastActivity = moment.unix(activity.events[0].created_at).fromNow();
                    }
                },
                function (result) {
                    $scope.activityError = result.data;
                },
            );
        }
    }
})();
