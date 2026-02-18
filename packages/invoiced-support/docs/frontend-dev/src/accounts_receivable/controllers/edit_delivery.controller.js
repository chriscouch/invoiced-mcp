(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('EditDeliveryController', EditDeliveryController);

    EditDeliveryController.$inject = [
        '$scope',
        '$filter',
        '$modalInstance',
        'selectedCompany',
        'Core',
        'InvoicedConfig',
        'Invoice',
        'InvoiceChasingCadence',
        'InvoiceChaseState',
        'invoice',
        'delivery',
        'chaseState',
    ];

    function EditDeliveryController(
        $scope,
        $filter,
        $modalInstance,
        selectedCompany,
        Core,
        InvoicedConfig,
        Invoice,
        InvoiceChasingCadence,
        InvoiceChaseState,
        invoice,
        delivery,
        chaseState,
    ) {
        /**
         * Initialization
         */

        let escapeHtml = $filter('escapeHtml');
        $scope.invoice = invoice;
        $scope.delivery = delivery;
        $scope.chaseSchedule = angular.copy(delivery.chase_schedule);
        $scope.chaseState = angular.copy(chaseState);
        $scope.lastAppliedCadence = null;
        $scope.cadences = []; // global level InvoiceChasingCadences (loaded below)
        $scope.editing = delivery.chase_schedule.length > 0;
        $scope.saving = false;
        $scope.deleting = false;
        $scope.canApplyCadence = canApplyCadence($scope.chaseState);

        // format emails for autocomplete
        $scope.emails = [];
        if (delivery.emails) {
            $scope.emails = delivery.emails.split(',').map(function (email) {
                return {
                    id: email,
                    email_address: email,
                };
            });
        }

        $scope.useCustomerContacts = $scope.emails.length === 0;
        loadInvoiceChasingCadences();

        // InvoiceCadenceEditor callback.
        //
        // NOTE: This bulk of the form which this controller handles
        // is managed by the InvoiceCadenceEditor directive. The directive
        // manages the manipulation of the invoice chasing schedule and
        // passes back modifications to this controller via a change
        // handler provided to the directive. This function is the
        // change handler.
        $scope.onChaseScheduleChange = function (chaseSchedule) {
            $scope.chaseSchedule = chaseSchedule;
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        // applies a global invoice chasing cadence to the chase schedule
        $scope.applyCadenceTemplate = function (cadenceTemplate) {
            // setting $scope.chaseSchedule will update the schedule
            // in the editor directive
            $scope.lastAppliedCadence = cadenceTemplate;
            $scope.chaseSchedule = angular.copy(cadenceTemplate.chase_schedule);
        };

        /**
         * Saves the InvoiceDelivery instance.
         */
        $scope.save = function () {
            $scope.saving = true;

            let emails = null;
            if (!$scope.useCustomerContacts) {
                emails = $scope.emails
                    .map(function (o) {
                        return o.email_address;
                    })
                    .join(',');
            }

            let cadenceId = null;
            // NOTE:
            // angular.toJson is used to check chasing cadence equality
            // between the delivery and the last applied template. It's
            // used because AngularJs adds keys to the step objects
            // for internal framework use which affect the normal equality
            // result.
            if (
                $scope.lastAppliedCadence &&
                angular.toJson($scope.chaseSchedule) == angular.toJson($scope.lastAppliedCadence.chase_schedule)
            ) {
                cadenceId = $scope.lastAppliedCadence.id;
            }

            let params = {
                disabled: false,
                emails: emails,
                chase_schedule: formatChaseSchedule($scope.chaseSchedule),
            };
            if (cadenceId) {
                params.cadence_id = cadenceId;
            }

            Invoice.setDelivery(
                {
                    id: invoice.id,
                },
                params,
                function (delivery) {
                    $scope.saving = false;
                    $modalInstance.close(delivery);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        };

        /**
         * Disables invoice chasing on the invoice.
         */
        $scope.disable = function ($event) {
            $scope.deleting = true;
            $event.preventDefault();
            Invoice.setDelivery(
                {
                    id: invoice.id,
                },
                {
                    disabled: true,
                },
                function (delivery) {
                    $scope.deleting = false;
                    $modalInstance.close(delivery);
                },
                function (result) {
                    $scope.deleting = false;
                    $scope.error = result.data;
                },
            );
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        /**
         * Loads all InvoiceChasingCadences.
         */
        function loadInvoiceChasingCadences() {
            InvoiceChasingCadence.findAll({ paginate: 'none' }, function (cadences) {
                $scope.cadences = cadences;
            });
        }

        /**
         * Formats chase schedule for edit request by converting
         * numeric strings to integer values.
         */
        function formatChaseSchedule(chaseSchedule) {
            return chaseSchedule.map(function (step) {
                let options = {};
                // convert numeric string values to integers
                angular.forEach(Object.keys(step.options), function (key) {
                    let value = step.options[key];
                    if (typeof value === 'string' && !isNaN(value)) {
                        options[key] = parseInt(value);
                    } else {
                        options[key] = value;
                    }
                });

                return {
                    id: step.id,
                    trigger: parseInt(step.trigger),
                    options: options,
                };
            });
        }

        /* Autocomplete */

        $scope.$watch('emails', formatEmailList);

        $scope.autocompleteOptions = {
            ajax: {
                url: InvoicedConfig.apiBaseUrl + '/autocomplete/emails?limit=3',
                dataType: 'json',
                params: {
                    headers: {
                        Authorization: selectedCompany.auth_header,
                    },
                    xhrFields: {
                        withCredentials: false,
                    },
                },
                data: function (term) {
                    return {
                        term: term,
                    };
                },
                results: function (data) {
                    angular.forEach(data, function (res) {
                        res.id = res.email_address;
                    });

                    return {
                        results: data,
                    };
                },
            },
            tags: true,
            minimumInputLength: 3,
            formatInputTooShort: false,
            width: '100%',
            dropdownCssClass: 'email-recipient-dropdown',
            createSearchChoice: function (term) {
                let atIndex = term.indexOf('@');
                if (atIndex === -1 || atIndex === term.length - 1) {
                    return null;
                }

                return {
                    email_address: term,
                    id: term,
                };
            },
            createSearchChoicePosition: 'top',
            formatSelection: function (email) {
                return email.email_address;
            },
            formatResult: function (email) {
                return '<div>' + email.email_address + '</div>';
            },
        };

        function formatEmailList(options) {
            for (let i in options) {
                if (!options[i].text) {
                    options[i].text = escapeHtml(options[i].email_address);
                }
            }
        }

        function canApplyCadence(state) {
            let states = InvoiceChaseState.build(state);
            for (let i = 0; i < states.length; i++) {
                if (states[i].sent || InvoiceChaseState.hasFailure(states[i])) {
                    return false;
                }
            }

            return true;
        }
    }
})();
