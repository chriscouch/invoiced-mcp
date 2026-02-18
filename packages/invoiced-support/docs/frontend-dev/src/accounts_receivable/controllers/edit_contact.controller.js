(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('EditContactController', EditContactController);

    EditContactController.$inject = [
        '$scope',
        '$modalInstance',
        '$translate',
        'selectedCompany',
        'Customer',
        'model',
        'customer',
    ];

    function EditContactController($scope, $modalInstance, $translate, selectedCompany, Customer, model, customer) {
        $scope.contact = angular.copy(model);
        if (typeof $scope.contact.role == 'object' && $scope.contact.role != null) {
            $scope.contact.role = $scope.contact.role.id;
        }

        $scope.customer = customer;
        $scope.company = selectedCompany;
        $scope.loadingContactRoles = false;
        $scope.contactRoles = [];
        changeCountry($scope.contact.country);

        loadContactRoles();

        $scope.changeCountry = changeCountry;

        $scope.save = function (contact) {
            $scope.saving = true;
            $scope.error = null;

            contact = angular.copy(contact);

            if (!contact.phone) {
                contact.sms_enabled = false;
            }

            let params = {
                name: contact.name,
                title: contact.title,
                department: contact.department,
                email: contact.email,
                phone: contact.phone,
                sms_enabled: contact.sms_enabled,
                send_new_invoices: contact.send_new_invoices,
                primary: contact.primary,
                address1: contact.address1,
                address2: contact.address2,
                city: contact.city,
                state: contact.state,
                postal_code: contact.postal_code,
                country: contact.country,
                role: contact.role ? contact.role : null,
            };

            if (typeof contact.id != 'undefined') {
                Customer.editContact(
                    {
                        id: customer.id,
                        subid: contact.id,
                        expand: 'role',
                    },
                    params,
                    function (contact) {
                        $scope.saving = false;

                        $modalInstance.close(contact);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                Customer.createContact(
                    {
                        id: customer.id,
                        expand: 'role',
                    },
                    params,
                    function (contact) {
                        $scope.saving = false;
                        $modalInstance.close(contact);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function loadContactRoles() {
            $scope.loadingContactRoles = true;
            Customer.contactRoles(
                function (roles) {
                    $scope.contactRoles = roles;
                    $scope.loadingContactRoles = false;
                },
                function (result) {
                    $scope.error = result.data;
                    $scope.loadingContactRoles = false;
                },
            );
        }

        function changeCountry(country) {
            if (typeof country !== 'string') {
                country = selectedCompany.country;
            }

            let locale = 'en_' + country;
            $scope.cityLabel = $translate.instant('address.city', {}, null, locale);
            $scope.stateLabel = $translate.instant('address.state', {}, null, locale);
            $scope.postalCodeLabel = $translate.instant('address.postal_code', {}, null, locale);
        }
    }
})();
