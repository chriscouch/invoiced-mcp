(function () {
    'use strict';

    angular.module('app.sending').controller('NewTextMessageController', NewTextMessageController);

    NewTextMessageController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        'Customer',
        'SmsTemplate',
        'customer',
        'documentId',
        'model',
    ];

    function NewTextMessageController(
        $scope,
        $modalInstance,
        $modal,
        Customer,
        SmsTemplate,
        customer,
        documentId,
        model,
    ) {
        $scope.customer = customer;
        $scope.loading = 0;
        $scope.contacts = [];
        $scope.to = {};
        $scope.loadedContacts = [];
        $scope.message = '{{company_name}}: You have a new invoice {{invoice_number}} {{url}}';

        $scope.addContactModal = function () {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/customers/edit-contact.html',
                controller: 'EditContactController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    model: function () {
                        return {
                            primary: true,
                        };
                    },
                    customer: function () {
                        return $scope.customer;
                    },
                },
            });

            modalInstance.result.then(
                function (contact) {
                    // merge into contacts
                    $scope.loadedContacts.push(contact);

                    // recalculate the contacts list
                    processContacts();

                    // select the contact
                    if (contact.phone && contact.sms_enabled) {
                        $scope.to[contact.id] = true;
                    }
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.applyTemplate = function (template) {
            $scope.message = template.message;
            $scope.selectedTemplate = template.id;
        };

        $scope.send = function () {
            $scope.sending = true;
            $scope.error = null;

            // parse recipients
            let to = [];
            let i;
            let findContact = function (contact) {
                if (contact.id == i) {
                    to.push({
                        name: contact.name,
                        phone: contact.phone,
                        country: contact.country,
                    });
                }
            };

            for (i in $scope.to) {
                if ($scope.to[i]) {
                    // find the contact
                    // TODO not efficient for a large number of contacts
                    angular.forEach($scope.contacts, findContact);
                }
            }

            model.sendTextMessage(
                {
                    id: documentId,
                },
                {
                    message: $scope.message,
                    to: to,
                },
                function () {
                    $scope.sending = false;
                    $modalInstance.close('Woot! Your text message has been added to the send queue.');
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

        loadContacts();
        loadTemplates();

        function loadContacts() {
            $scope.loading++;
            Customer.contacts(
                {
                    id: $scope.customer.id,
                    sort: 'name ASC',
                },
                function (contacts) {
                    $scope.loading--;
                    $scope.loadedContacts = contacts;
                    processContacts();
                },
            );
        }

        function processContacts() {
            $scope.contacts = [];
            angular.forEach($scope.loadedContacts, function (contact) {
                if (contact.sms_enabled && contact.phone) {
                    $scope.contacts.push(contact);

                    if (contact.primary && typeof $scope.to[contact.id] === 'undefined') {
                        $scope.to[contact.id] = true;
                    }
                }
            });
        }

        function loadTemplates() {
            $scope.loadingTemplates = true;
            SmsTemplate.findAll(
                { paginate: 'none' },
                function (smsTemplates) {
                    $scope.smsTemplates = smsTemplates;
                    $scope.loadingTemplates = false;
                },
                function () {
                    $scope.loadingTemplates = false;
                },
            );
        }
    }
})();
