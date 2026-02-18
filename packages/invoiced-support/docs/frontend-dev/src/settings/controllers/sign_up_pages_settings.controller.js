/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('SignUpPagesSettingsController', SignUpPagesSettingsController);

    SignUpPagesSettingsController.$inject = [
        '$scope',
        '$modal',
        'LeavePageWarning',
        'SignUpPage',
        'selectedCompany',
        'Core',
        'Feature',
    ];

    function SignUpPagesSettingsController(
        $scope,
        $modal,
        LeavePageWarning,
        SignUpPage,
        selectedCompany,
        Core,
        Feature,
    ) {
        $scope.hasFeature = Feature.hasFeature('subscriptions') || Feature.hasFeature('autopay');
        $scope.supportsItems = Feature.hasFeature('recurring_catalog_items');
        $scope.company = angular.copy(selectedCompany);

        $scope.signUpPages = [];
        $scope.deleting = {};
        $scope.search = '';

        $scope.editSignUpPageModal = function (page) {
            LeavePageWarning.block();

            page = page || false;

            const modalInstance = $modal.open({
                templateUrl: 'sign_up_pages/edit-sign-up-page.html',
                controller: 'EditSignUpPageController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    page: function () {
                        if (!page) {
                            return false;
                        }

                        return page;
                    },
                    currency: function () {
                        return $scope.company.currency;
                    },
                },
            });

            modalInstance.result.then(
                function (_page) {
                    LeavePageWarning.unblock();

                    if (page) {
                        angular.extend(page, _page);
                    } else {
                        $scope.signUpPages.push(_page);
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.delete = function (page) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this sign up page?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[page.id] = true;
                        $scope.error = null;

                        SignUpPage.delete(
                            {
                                id: page.id,
                            },
                            function () {
                                $scope.deleting[page.id] = false;

                                // remove locally
                                for (let i in $scope.signUpPages) {
                                    if ($scope.signUpPages[i].id == page.id) {
                                        $scope.signUpPages.splice(i, 1);
                                        break;
                                    }
                                }
                            },
                            function (result) {
                                $scope.deleting[page.id] = false;
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        $scope.urlModal = function (url) {
            $modal.open({
                templateUrl: 'components/views/url-modal.html',
                controller: 'URLModalController',
                resolve: {
                    url: function () {
                        return url;
                    },
                    customer: function () {
                        return null;
                    },
                },
            });
        };

        Core.setTitle('Sign Up Pages');

        load();

        function load() {
            $scope.loading = true;

            SignUpPage.all(
                function (signUpPages) {
                    $scope.loading = false;
                    $scope.signUpPages = signUpPages;
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
