/* globals moment */
(function () {
    'use strict';

    angular.module('app.reports').controller('ReportStandardParametersController', ReportStandardParametersController);

    ReportStandardParametersController.$inject = [
        '$scope',
        '$modalInstance',
        '$timeout',
        'MetadataCaster',
        'CustomField',
        'selectedCompany',
        'availableParameters',
        'parameters',
    ];

    function ReportStandardParametersController(
        $scope,
        $modalInstance,
        $timeout,
        MetadataCaster,
        CustomField,
        selectedCompany,
        availableParameters,
        parameters,
    ) {
        $scope.availableParameters = angular.extend(
            {
                dateRange: false,
                currency: false,
                groupBy: false,
                taxRecognitionDate: false,
                customerCustomFields: false,
                invoiceCustomFields: false,
            },
            availableParameters,
        );
        $scope.reportParameters = angular.extend(
            {
                $dateRange: {
                    period: ['days', 30],
                },
                $currency: selectedCompany.currency,
                $taxRecognitionDate: 'invoice',
                $groupBy: availableParameters.defaultGroupBy || 'none',
                $customerMetadata: {},
                $invoiceMetadata: {},
            },
            parameters,
        );
        if (typeof $scope.reportParameters.$dateRange.start !== 'undefined') {
            $scope.reportParameters.$dateRange.start = moment(
                $scope.reportParameters.$dateRange.start,
                'YYYY-MM-DD',
            ).toDate();
            $scope.reportParameters.$dateRange.end = moment(
                $scope.reportParameters.$dateRange.end,
                'YYYY-MM-DD',
            ).toDate();
        }
        $scope.availableCurrencies = selectedCompany.currencies;
        $scope.customerCustomFields = [];
        $scope.selectedCustomerCustomFields = {};
        $scope.invoiceCustomFields = [];
        $scope.selectedInvoiceCustomFields = {};

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.generate = function (reportParameters, selectedCustomerCustomFields, selectedInvoiceCustomFields) {
            let parameters = {};

            if (availableParameters.dateRange) {
                parameters.$dateRange = {
                    period: reportParameters.$dateRange.period,
                    start: moment(reportParameters.$dateRange.start).format('YYYY-MM-DD'),
                    end: moment(reportParameters.$dateRange.end).format('YYYY-MM-DD'),
                };
            }

            if (availableParameters.currency && selectedCompany.currencies.length > 1) {
                parameters.$currency = reportParameters.$currency;
            }

            if (availableParameters.groupBy) {
                parameters.$groupBy = reportParameters.$groupBy;
            }

            if (availableParameters.taxRecognitionDate) {
                parameters.$taxRecognitionDate = reportParameters.$taxRecognitionDate;
            }

            // custom field filters
            if (availableParameters.invoiceCustomFields) {
                let metadata = {};
                angular.forEach(selectedInvoiceCustomFields, function (enabled, key) {
                    if (enabled && typeof $scope.invoiceCustomFieldIds[key] !== 'undefined') {
                        metadata[key] = reportParameters.$invoiceMetadata[key];
                    }
                });

                MetadataCaster.marshalForInvoiced('invoice', metadata, function (_metadata) {
                    if (Object.keys(_metadata).length > 0) {
                        parameters.$invoiceMetadata = _metadata;
                    }
                    generateInvoiceFields(
                        reportParameters,
                        selectedCustomerCustomFields,
                        selectedInvoiceCustomFields,
                        parameters,
                    );
                });
            } else {
                generateInvoiceFields(
                    reportParameters,
                    selectedCustomerCustomFields,
                    selectedInvoiceCustomFields,
                    parameters,
                );
            }
        };

        function generateInvoiceFields(
            reportParameters,
            selectedCustomerCustomFields,
            selectedInvoiceCustomFields,
            parameters,
        ) {
            if (availableParameters.invoiceCustomFields) {
                let metadata = {};
                angular.forEach(selectedInvoiceCustomFields, function (enabled, key) {
                    if (enabled && typeof $scope.invoiceCustomFieldIds[key] !== 'undefined') {
                        metadata[key] = reportParameters.$invoiceMetadata[key];
                    }
                });

                MetadataCaster.marshalForInvoiced('invoice', metadata, function (_metadata) {
                    if (Object.keys(_metadata).length > 0) {
                        parameters.$invoiceMetadata = _metadata;
                    }
                    generateCustomerFields(
                        reportParameters,
                        selectedCustomerCustomFields,
                        selectedInvoiceCustomFields,
                        parameters,
                    );
                });
            } else {
                generateCustomerFields(
                    reportParameters,
                    selectedCustomerCustomFields,
                    selectedInvoiceCustomFields,
                    parameters,
                );
            }
        }

        function generateCustomerFields(
            reportParameters,
            selectedCustomerCustomFields,
            selectedInvoiceCustomFields,
            parameters,
        ) {
            if (availableParameters.customerCustomFields) {
                let metadata = {};
                angular.forEach(selectedCustomerCustomFields, function (enabled, key) {
                    if (enabled && typeof $scope.customerCustomFieldIds[key] !== 'undefined') {
                        metadata[key] = reportParameters.$customerMetadata[key];
                    }
                });

                MetadataCaster.marshalForInvoiced('customer', metadata, function (_metadata) {
                    if (Object.keys(_metadata).length > 0) {
                        parameters.$customerMetadata = _metadata;
                    }
                    $modalInstance.close(parameters);
                });
            } else {
                $modalInstance.close(parameters);
            }
        }

        loadCustomFields();

        function loadCustomFields() {
            CustomField.all(
                function (customFields) {
                    $scope.customerCustomFields = [];
                    $scope.customerCustomFieldIds = {};
                    $scope.invoiceCustomFields = [];
                    $scope.invoiceCustomFieldIds = {};
                    angular.forEach(customFields, function (customField) {
                        if (customField.object === 'invoice' || customField.object === 'credit_note') {
                            if (typeof $scope.invoiceCustomFieldIds[customField.id] !== 'undefined') {
                                return;
                            }

                            $scope.invoiceCustomFields.push(customField);
                            $scope.invoiceCustomFieldIds[customField.id] = true;

                            if (customField.choices.length > 0) {
                                angular.forEach($scope.availableReports, function (report) {
                                    if (report.groupByOptions) {
                                        report.groupByOptions.push({
                                            text: customField.name,
                                            id: 'metadata:' + customField.id,
                                        });
                                    }
                                });
                            }
                        } else if (customField.object === 'customer') {
                            $scope.customerCustomFields.push(customField);
                            $scope.customerCustomFieldIds[customField.id] = true;
                        }
                    });

                    // After loading custom fields, if there are no parameters for the
                    // user to select because there are no custom fields then we should
                    // proceed with generating the report
                    if (!hasParameters()) {
                        // The timeout is necessary or else when the custom fields cache is
                        // used the $modalInstance.close() function does not work.
                        $timeout(function () {
                            $scope.generate(
                                $scope.reportParameters,
                                $scope.selectedCustomerCustomFields,
                                $scope.selectedInvoiceCustomFields,
                            );
                        });
                    } else {
                        // Now we can un-hide the modal. The timeout is necessary.
                        $timeout(function () {
                            $('.report-standard-parameters-modal').removeClass('report-standard-parameters-modal');
                        });
                    }
                },
                function (result) {
                    $scope.error = result.data;
                    // Now we can un-hide the modal. The timeout is necessary.
                    $timeout(function () {
                        $('.report-standard-parameters-modal').removeClass('report-standard-parameters-modal');
                    });
                },
            );

            // This function should only be called AFTER custom fields are loaded
            function hasParameters() {
                if (availableParameters.customerCustomFields && $scope.customerCustomFields.length > 0) {
                    return true;
                }

                if (availableParameters.invoiceCustomFields && $scope.invoiceCustomFields.length > 0) {
                    return true;
                }

                let availableParameters2 = angular.copy(availableParameters);
                delete availableParameters2.customerCustomFields;
                delete availableParameters2.invoiceCustomFields;

                let hasParameters = false;
                angular.forEach(availableParameters2, function (value) {
                    hasParameters = hasParameters || value;
                });

                return hasParameters;
            }
        }
    }
})();
