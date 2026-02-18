/* globals _ */
(function () {
    'use strict';

    angular.module('app.sending').controller('NewEmailController', NewEmailController);

    NewEmailController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        'CurrentUser',
        'Customer',
        'Invoice',
        'EmailTemplate',
        'Settings',
        'options',
        'InvoicedConfig',
        'selectedCompany',
        'Feature',
    ];

    function NewEmailController(
        $scope,
        $modal,
        $modalInstance,
        CurrentUser,
        Customer,
        Invoice,
        EmailTemplate,
        Settings,
        options,
        InvoicedConfig,
        selectedCompany,
        Feature,
    ) {
        $scope.options = angular.extend(
            {
                message: '',
                bcc: '',
            },
            options,
        );
        $scope.contacts = [];
        $scope.to = {};
        $scope.loadedContacts = [];
        $scope.company = selectedCompany;
        $scope.department = null;
        $scope.hasInvoiceDistributions = Feature.hasFeature('invoice_distributions');

        $scope.editCustomerModal = function () {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/customers/edit-customer.html',
                controller: 'EditCustomerController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    model: function () {
                        return $scope.options.customer;
                    },
                },
            });

            modalInstance.result.then(
                function (customer) {
                    angular.extend($scope.options.customer, customer);

                    // recalculate the contacts list
                    processContacts();
                },
                function () {
                    // canceled
                },
            );
        };

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
                        return $scope.options.customer;
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
                    if (contact.email) {
                        $scope.to[contact.id] = true;
                    }
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.valid = function () {
            // must have at least one recpient selected
            for (let i in $scope.to) {
                if ($scope.to[i]) {
                    return true;
                }
            }

            return false;
        };

        $scope.copyMe = function () {
            let recipients = $scope.options.bcc.split(',');
            recipients.push(CurrentUser.profile.email);
            $scope.options.bcc = _.uniq(_.without(recipients, '')).join(',');
        };

        $scope.applyTemplate = function (template) {
            $scope.options.subject = template.subject;
            $scope.options.message = template.body;
            $scope.options.template = template.id;
        };

        $scope.send = function (options) {
            let params = angular.copy(options);

            delete params.customer;
            delete params.id;
            delete params.templateName;
            delete params.type;
            if (params.model) {
                delete params.model;
            }

            if (params.title) {
                delete params.title;
            }

            let i;
            if (params.options) {
                for (i in params.options) {
                    params[i] = params.options[i];
                }
                delete params.options;
            }

            $scope.saving = true;
            $scope.error = null;

            // parse recipients
            params.to = [];
            let findContact = function (contact) {
                if (contact.id == i) {
                    params.to.push({
                        name: contact.name,
                        email: contact.email,
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

            $scope.options.model.email(
                {
                    id: options.id,
                },
                params,
                function () {
                    $scope.saving = false;
                    $modalInstance.close('Woot! Your email has been added to the send queue.');
                },
                function (result) {
                    $scope.saving = false;
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

        if (typeof InvoicedConfig.templates.email[$scope.options.template] != 'undefined') {
            $scope.applyTemplate(InvoicedConfig.templates.email[$scope.options.template]);
        }

        loadSettings();
        loadContacts();
        loadTemplates(options.type);
        if ($scope.hasInvoiceDistributions && options.type === 'invoice') {
            loadInvoiceDistributionSettings(options.id);
        }

        function loadSettings() {
            Settings.accountsReceivable(function (settings) {
                $scope.options.bcc = settings.bcc;
            });
        }

        function loadContacts() {
            $scope.loading = 1;

            // load customer profile
            if (typeof $scope.options.customer.email === 'undefined') {
                $scope.loading++;
                Customer.find(
                    {
                        id: $scope.options.customer.id,
                    },
                    function (customer) {
                        angular.extend($scope.options.customer, customer);

                        processContacts();

                        $scope.loading--;
                    },
                );
            }

            // load contacts
            Customer.contacts(
                {
                    id: $scope.options.customer.id,
                    sort: 'name ASC',
                },
                function (contacts) {
                    $scope.loadedContacts = contacts;

                    processContacts();

                    $scope.loading--;
                },
            );
        }

        function loadTemplates(type) {
            if (!type) {
                return;
            }

            $scope.loadingTemplates = true;
            EmailTemplate.findAll(
                {
                    'filter[type]': type,
                    paginate: 'none',
                },
                function (emailTemplates) {
                    $scope.emailTemplates = emailTemplates;
                    $scope.loadingTemplates = false;

                    angular.forEach(emailTemplates, function (template) {
                        if (template.id == $scope.options.template) {
                            $scope.applyTemplate(template);
                        }
                    });
                },
                function () {
                    $scope.loadingTemplates = false;
                },
            );
        }

        function loadInvoiceDistributionSettings(id) {
            Invoice.distributions(
                {
                    id: id,
                },
                function (distributions) {
                    if (distributions.length === 1) {
                        $scope.department = distributions[0].department;
                        processContacts();
                    }
                },
            );
        }

        function processContacts() {
            $scope.contacts = [];
            let hasCustomerEmail = false;
            angular.forEach($scope.loadedContacts, function (contact) {
                if (contact.email) {
                    $scope.contacts.push(contact);

                    if ($scope.department) {
                        if ($scope.department == contact.department) {
                            $scope.to[contact.id] = true;
                        }
                    } else if (contact.primary) {
                        $scope.to[contact.id] = true;
                    }

                    if (contact.email == $scope.options.customer.email) {
                        hasCustomerEmail = true;
                    }
                }
            });

            if (!hasCustomerEmail && $scope.options.customer.email) {
                $scope.contacts.splice(0, 0, {
                    id: -1,
                    name: $scope.options.customer.name,
                    email: $scope.options.customer.email,
                    primary: true,
                });

                if (!$scope.department) {
                    $scope.to[-1] = true;
                }
            }
        }
    }
})();
