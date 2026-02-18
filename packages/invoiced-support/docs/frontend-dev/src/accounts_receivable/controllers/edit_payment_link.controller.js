(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('EditPaymentLinkController', EditPaymentLinkController);

    EditPaymentLinkController.$inject = [
        '$scope',
        '$state',
        '$stateParams',
        '$modal',
        'PaymentLink',
        'PaymentLinkHelper',
        'LeavePageWarning',
        'Core',
        'selectedCompany',
        'Customer',
    ];

    function EditPaymentLinkController(
        $scope,
        $state,
        $stateParams,
        $modal,
        PaymentLink,
        PaymentLinkHelper,
        LeavePageWarning,
        Core,
        selectedCompany,
        Customer,
    ) {
        $scope.save = save;
        $scope.addLineItem = addLineItem;
        $scope.removeLineItem = removeLineItem;
        $scope.addField = addField;
        $scope.removeField = removeField;
        $scope.paymentLink = {
            customer: $stateParams.customer || null,
            reusable: false,
            collect_billing_address: false,
            collect_shipping_address: false,
            collect_phone_number: false,
            terms_of_service_url: null,
            after_completion_url: null,
            currency: selectedCompany.currency,
            items: [],
            fields: [],
        };
        $scope.paymentLinkTotal = 0;
        const isDuplicate = $state.current.name === 'manage.payment_link.duplicate';

        $scope.sortableOptions = {
            handle: '.sortable-handle',
        };

        LeavePageWarning.watchForm($scope, 'modelForm');
        load();

        $scope.$watch(
            'paymentLink',
            function () {
                $scope.paymentLinkTotal = PaymentLinkHelper.calculateTotalPrice($scope.paymentLink);
            },
            true,
        );

        function load() {
            Core.setTitle('New Payment Link');
            if (!$stateParams.id) {
                addLineItem($scope.paymentLink);

                // check if a customer was provided through the route
                if ($stateParams.customer) {
                    Customer.find(
                        {
                            id: $stateParams.customer,
                        },
                        function (customer) {
                            $scope.paymentLink.customer = customer;
                        },
                        function (result) {
                            Core.showMessage(result.data.message, 'error');
                        },
                    );
                }

                return;
            } else if (!isDuplicate) {
                Core.setTitle('Edit Payment Link');
            }

            $scope.loading = true;
            PaymentLink.find(
                {
                    id: $stateParams.id,
                    include: 'items,fields',
                },
                function (paymentLink) {
                    $scope.loading = false;

                    if (isDuplicate) {
                        $scope.paymentLink = {
                            customer: paymentLink.customer,
                            reusable: paymentLink.reusable,
                            collect_billing_address: paymentLink.collect_billing_address,
                            collect_shipping_address: paymentLink.collect_shipping_address,
                            collect_phone_number: paymentLink.collect_phone_number,
                            terms_of_service_url: paymentLink.terms_of_service_url,
                            after_completion_url: paymentLink.after_completion_url,
                            currency: paymentLink.currency,
                            items: [],
                            fields: [],
                        };

                        angular.forEach(paymentLink.items, function (lineItem) {
                            $scope.paymentLink.items.push({
                                description: lineItem.description,
                                amount: lineItem.amount,
                            });
                        });

                        angular.forEach(paymentLink.fields, function (field) {
                            $scope.paymentLink.fields.push({
                                object_type: field.object_type,
                                custom_field_id: field.custom_field_id,
                                required: field.required,
                            });
                        });
                    } else {
                        $scope.paymentLink = paymentLink;
                    }

                    $scope.paymentLink.link_template = $scope.paymentLink.items.length === 0;
                },
                function (result) {
                    $scope.loading = false;
                    $scope.error = result.data.message;
                },
            );
        }

        function addLineItem(paymentLink) {
            paymentLink.items.push({
                description: '',
                amount: null,
            });
        }

        function removeLineItem(paymentLink, $index) {
            paymentLink.items.splice($index, 1);
        }

        function addField(paymentLink, objectType) {
            const modalInstance = $modal.open({
                templateUrl: 'metadata/views/add-custom-field.html',
                controller: 'AddCustomFieldController',
                windowClass: 'add-custom-field-modal',
                resolve: {
                    type: function () {
                        return objectType;
                    },
                },
            });

            modalInstance.result.then(
                function (customField) {
                    let found = false; // prevent the same field from being added twice
                    angular.forEach(paymentLink.fields, function (field) {
                        if (field.object_type === objectType && field.custom_field_id === customField.id) {
                            found = true;
                        }
                    });

                    if (!found) {
                        paymentLink.fields.push({
                            object_type: objectType,
                            custom_field_id: customField.id,
                            required: customField.type !== 'boolean',
                        });
                    }
                },
                function () {
                    // canceled
                },
            );
        }

        function removeField(paymentLink, $index) {
            paymentLink.fields.splice($index, 1);
        }

        function save(paymentLink) {
            $scope.saving = true;

            let params = {
                customer: paymentLink.customer ? parseInt(paymentLink.customer.id) : null,
                reusable: paymentLink.reusable,
                collect_billing_address: paymentLink.collect_billing_address,
                collect_shipping_address: paymentLink.collect_shipping_address,
                collect_phone_number: paymentLink.collect_phone_number,
                terms_of_service_url: paymentLink.terms_of_service_url,
                after_completion_url: paymentLink.after_completion_url,
                currency: paymentLink.currency,
                items: [],
                fields: [],
            };

            if (paymentLink.link_template) {
                params.name = 'Template Link';
            } else {
                params.name = paymentLink.items.length > 0 ? paymentLink.items[0].description.slice(0, 50) : '';
                if (!params.name) {
                    params.name = 'Payment Link';
                }

                angular.forEach(paymentLink.items, function (lineItem) {
                    params.items.push({
                        id: lineItem.id || null,
                        description: lineItem.description,
                        amount: lineItem.amount,
                    });
                });
            }

            angular.forEach(paymentLink.fields, function (field) {
                params.fields.push({
                    id: field.id || null,
                    object_type: field.object_type,
                    custom_field_id: field.custom_field_id,
                    required: field.required,
                });
            });

            if (paymentLink.id) {
                PaymentLink.edit(
                    { id: paymentLink.id },
                    params,
                    function () {
                        $scope.saving = false;
                        LeavePageWarning.unblock();
                        $state.go('manage.payment_link.view.summary', { id: paymentLink.id });
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data.message;
                    },
                );
            } else {
                PaymentLink.create(
                    params,
                    function (paymentLink2) {
                        $scope.saving = false;
                        LeavePageWarning.unblock();
                        $state.go('manage.payment_link.view.summary', { id: paymentLink2.id });
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data.message;
                    },
                );
            }
        }
    }
})();
