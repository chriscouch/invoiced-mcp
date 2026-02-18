(function () {
    'use strict';

    angular.module('app.sign_up_pages').controller('AssignSignUpPageController', AssignSignUpPageController);

    AssignSignUpPageController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        'SignUpPage',
        'Customer',
        'Core',
        'customer',
        'selectedCompany',
    ];

    function AssignSignUpPageController(
        $scope,
        $modalInstance,
        $modal,
        SignUpPage,
        Customer,
        Core,
        customer,
        selectedCompany,
    ) {
        $scope.company = selectedCompany;
        $scope.customer = angular.copy(customer);
        $scope.hasSignUpPage = !!customer.sign_up_page;

        $scope.newSignUpPageModal = function () {
            const modalInstance = $modal.open({
                templateUrl: 'sign_up_pages/edit-sign-up-page.html',
                controller: 'EditSignUpPageController',
                resolve: {
                    page: function () {
                        return false;
                    },
                    currency: function () {
                        return $scope.company.currency;
                    },
                },
                backdrop: false,
                keyboard: false,
            });

            modalInstance.result.then(
                function (page) {
                    $scope.signUpPages.push(page);
                    $scope.customer.sign_up_page = page.id;
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.save = function (customer) {
            let pageId = customer.sign_up_page;
            if (!$scope.hasSignUpPage) {
                pageId = null;
            }

            $scope.saving = true;
            Customer.edit(
                {
                    id: customer.id,
                },
                {
                    sign_up_page: pageId,
                },
                function (customer) {
                    $scope.saving = false;
                    $modalInstance.close(customer);
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

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
