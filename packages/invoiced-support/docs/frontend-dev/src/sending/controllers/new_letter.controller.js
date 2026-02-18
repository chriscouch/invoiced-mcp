(function () {
    'use strict';

    angular.module('app.sending').controller('NewLetterController', NewLetterController);

    NewLetterController.$inject = [
        '$scope',
        '$modalInstance',
        '$translate',
        'Customer',
        'selectedCompany',
        'customer',
        'documentId',
        'model',
        'options',
    ];

    function NewLetterController(
        $scope,
        $modalInstance,
        $translate,
        Customer,
        selectedCompany,
        customer,
        documentId,
        model,
        options,
    ) {
        $scope.customer = customer;
        $scope.contacts = [];
        $scope.letter = angular.copy(options);

        $scope.changeCountry = changeCountry;
        $scope.useContact = useContact;
        $scope.send = function (letter) {
            $scope.sending = true;
            $scope.error = null;

            model.sendLetter(
                {
                    id: documentId,
                },
                letter,
                function () {
                    $scope.sending = false;
                    $modalInstance.close('Woot! Your letter has been added to the send queue.');
                },
                function (result) {
                    $scope.sending = false;
                    $scope.error = result.data;
                },
            );
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
            $scope.letter.name = contact.name;
            $scope.letter.address1 = contact.address1;
            $scope.letter.address2 = contact.address2;
            $scope.letter.city = contact.city;
            $scope.letter.state = contact.state;
            $scope.letter.postal_code = contact.postal_code;
            $scope.letter.country = contact.country;
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
