(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('AddShippingController', AddShippingController);

    AddShippingController.$inject = [
        '$scope',
        '$modalInstance',
        '$translate',
        'selectedCompany',
        'Core',
        'Customer',
        'customer',
        'shipTo',
    ];

    function AddShippingController(
        $scope,
        $modalInstance,
        $translate,
        selectedCompany,
        Core,
        Customer,
        customer,
        shipTo,
    ) {
        $scope.contacts = [];
        $scope.customer = customer;
        $scope.shipTo = angular.copy(shipTo);
        $scope.hasAddress = !!shipTo;
        if (!shipTo) {
            $scope.shipTo = {};
            useContact(customer);
        }

        $scope.changeCountry = changeCountry;
        $scope.useContact = useContact;

        $scope.clear = function () {
            $modalInstance.close(null);
        };

        $scope.save = function (shipTo) {
            $modalInstance.close(shipTo);
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        loadContacts(customer);
        useContact(customer);

        function loadContacts(customer) {
            Customer.contacts(
                {
                    id: customer.id,
                    sort: 'name ASC',
                },
                function (contacts) {
                    angular.forEach(contacts, function (contact) {
                        if (contact.address1) {
                            $scope.contacts.push(contact);
                        }
                    });
                },
                function () {
                    // ignore errors
                },
            );
        }

        function useContact(contact) {
            $scope.shipTo.name = customer.name;
            $scope.shipTo.attention_to = contact.name !== customer.name ? contact.name : '';
            $scope.shipTo.address1 = contact.address1;
            $scope.shipTo.address2 = contact.address2;
            $scope.shipTo.city = contact.city;
            $scope.shipTo.state = contact.state;
            $scope.shipTo.postal_code = contact.postal_code;
            $scope.shipTo.country = contact.country;
            changeCountry(contact.country);
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
