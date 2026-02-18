/* globals inflection, vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('AppearanceSettingsController', AppearanceSettingsController);

    AppearanceSettingsController.$inject = [
        '$scope',
        '$modal',
        'LeavePageWarning',
        'Settings',
        'Theme',
        'PdfTemplate',
        'selectedCompany',
        'InvoicedConfig',
        'Core',
        'Feature',
    ];

    function AppearanceSettingsController(
        $scope,
        $modal,
        LeavePageWarning,
        Settings,
        Theme,
        PdfTemplate,
        selectedCompany,
        InvoicedConfig,
        Core,
        Feature,
    ) {
        $scope.tab = 'design';
        $scope.hasCustomTemplates = Feature.hasFeature('custom_templates');
        $scope.hasEstimates = Feature.hasFeature('estimates');

        $scope.fields = [
            {
                title: 'Document Headers',
            },
            {
                name: 'Invoice Title',
                property: 'header',
            },
            {
                name: 'From Title',
                property: 'from_title',
            },
            {
                name: 'Bill To Title',
                property: 'to_title',
            },
            {
                name: 'Ship To Title',
                property: 'ship_to_title',
            },
            {
                name: 'Account Number Title',
                property: 'customer_number_title',
            },
            {
                name: 'Purchase Order Title',
                property: 'purchase_order_title',
            },
            {
                name: 'Date Title',
                property: 'date_title',
            },
            {
                name: 'Payment Terms Title',
                property: 'payment_terms_title',
            },
            {
                name: 'Due Date Title',
                property: 'due_date_title',
            },
            {
                name: 'Balance Title',
                property: 'balance_title',
            },
            {
                hr: true,
                title: 'Line Items',
            },
            {
                name: 'Item Column Title',
                property: 'item_header',
            },
            {
                name: 'Quantity Column Title',
                property: 'quantity_header',
            },
            {
                name: 'Rate Column Title',
                property: 'unit_cost_header',
            },
            {
                name: 'Amount Column Title',
                property: 'amount_header',
            },
            {
                hr: true,
                title: 'Document Footers',
            },
            {
                name: 'Subtotal Title',
                property: 'subtotal_title',
            },
            {
                name: 'Total Title',
                property: 'total_title',
            },
            {
                name: 'Amount Paid Title',
                property: 'amount_paid_title',
            },
            {
                name: 'Notes Title',
                property: 'notes_title',
            },
            {
                name: 'Footer Title',
                property: 'terms_title',
            },
            {
                hr: true,
                title: 'Receipts',
            },
            {
                name: 'Receipt Title',
                property: 'header_receipt',
            },
            {
                name: 'Receipt Amount Title',
                property: 'amount_title',
            },
            {
                name: 'Payment Method Title',
                property: 'payment_method_title',
            },
            {
                name: 'Check # Title',
                property: 'check_no_title',
            },
        ];

        if ($scope.hasEstimates) {
            // add in estimate field titles
            // below Invoice Title
            $scope.fields.splice(2, 0, {
                name: 'Estimate Title',
                property: 'header_estimate',
            });
        }

        $scope.loading = 0;

        let documentTypes = ['credit_note', 'estimate', 'invoice', 'receipt', 'statement'];
        $scope.templateOptions = {};

        $scope.save = saveTheme;
        $scope.preview = preview;

        $scope.changedStyle = function (style) {
            $scope.styleName = inflection.titleize(style);
            generateTemplateOptions();
        };

        $scope.newPdfTemplate = function (documentType) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'themes/edit-pdf-template.html',
                controller: 'EditPdfTemplateController',
                backdrop: 'static',
                keyboard: false,
                windowClass: 'edit-pdf-template-modal',
                resolve: {
                    documentType: function () {
                        return documentType;
                    },
                    pdfTemplate: function () {
                        return false;
                    },
                },
            });

            modalInstance.result.then(
                function (pdfTemplate) {
                    LeavePageWarning.unblock();

                    $scope.pdfTemplates.push(pdfTemplate);
                    generateTemplateOptions();
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.clonePdfTemplateModal = function (pdfTemplate) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'themes/edit-pdf-template.html',
                controller: 'EditPdfTemplateController',
                backdrop: 'static',
                keyboard: false,
                windowClass: 'edit-pdf-template-modal',
                resolve: {
                    documentType: function () {
                        return pdfTemplate.document_type;
                    },
                    pdfTemplate: function () {
                        pdfTemplate = angular.copy(pdfTemplate);
                        delete pdfTemplate.id;
                        pdfTemplate.name += ' (copy)';
                        return pdfTemplate;
                    },
                },
            });

            modalInstance.result.then(
                function (_pdfTemplate) {
                    LeavePageWarning.unblock();

                    $scope.pdfTemplates.push(_pdfTemplate);
                    generateTemplateOptions();
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.editPdfTemplateModal = function (pdfTemplate) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'themes/edit-pdf-template.html',
                controller: 'EditPdfTemplateController',
                backdrop: 'static',
                keyboard: false,
                windowClass: 'edit-pdf-template-modal',
                resolve: {
                    documentType: function () {
                        return pdfTemplate.document_type;
                    },
                    pdfTemplate: function () {
                        return pdfTemplate;
                    },
                },
            });

            modalInstance.result.then(
                function (_pdfTemplate) {
                    LeavePageWarning.unblock();

                    angular.extend(pdfTemplate, _pdfTemplate);
                    generateTemplateOptions();
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.deletePdfTemplate = function (pdfTemplate) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this custom template?',
                callback: function (result) {
                    if (result) {
                        PdfTemplate.delete(
                            {
                                id: pdfTemplate.id,
                            },
                            function () {
                                $scope.error = null;

                                Core.flashMessage('Your custom template has been deleted.', 'success');

                                loadPdfTemplates();
                            },
                            function (result) {
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        LeavePageWarning.watchForm($scope, 'themeForm');

        Core.setTitle('Appearance');

        loadThemes();
        loadPdfTemplates();

        let themes = [];
        let isExisting = false;

        function loadThemes() {
            $scope.loading++;

            Settings.accountsReceivable(
                function (settings) {
                    Theme.findAll(
                        { paginate: 'none' },
                        function (_themes) {
                            $scope.loading--;
                            themes = _themes;

                            // select the default theme
                            angular.forEach(themes, function (theme) {
                                if (theme.id == settings.default_theme_id) {
                                    $scope.theme = theme;
                                    isExisting = true;
                                }
                            });

                            // if no default is available then fallback to base theme
                            if (!isExisting) {
                                $scope.theme = angular.copy(Theme.defaultTheme);
                            }

                            $scope.styleName = inflection.titleize($scope.theme.style);

                            // give an artificial ID to help with ng-options
                            angular.forEach(
                                [
                                    'credit_note_template_id',
                                    'estimate_template_id',
                                    'invoice_template_id',
                                    'receipt_template_id',
                                    'statement_template_id',
                                ],
                                function (key) {
                                    if (!$scope.theme[key]) {
                                        $scope.theme[key] = -1;
                                    }
                                },
                            );

                            generateTemplateOptions();
                        },
                        function (result) {
                            $scope.loading--;
                            Core.showMessage(result.data.message, 'error');
                        },
                    );
                },
                function (result) {
                    $scope.loading--;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadPdfTemplates() {
            $scope.loading++;

            PdfTemplate.findAll(
                {
                    sort: 'name ASC',
                    paginate: 'none',
                },
                function (pdfTemplates) {
                    $scope.loading--;
                    $scope.pdfTemplates = pdfTemplates;
                    generateTemplateOptions();
                },
                function (result) {
                    $scope.loading--;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function generateTemplateOptions() {
            angular.forEach(documentTypes, function (key) {
                $scope.templateOptions[key] = [
                    {
                        id: -1,
                        name: $scope.styleName,
                    },
                ];
            });

            angular.forEach($scope.pdfTemplates, function (pdfTemplate) {
                $scope.templateOptions[pdfTemplate.document_type].push({
                    id: pdfTemplate.id,
                    name: pdfTemplate.name,
                });
            });
        }

        function saveTheme(theme) {
            theme = angular.copy(theme);

            $scope.saving = true;
            $scope.error = null;

            let id = theme.id;
            delete theme.id;
            delete theme.date_format;
            delete theme.created_at;
            delete theme.updated_at;

            // handle an artificial ID to help with ng-options
            angular.forEach(
                [
                    'credit_note_template_id',
                    'estimate_template_id',
                    'invoice_template_id',
                    'receipt_template_id',
                    'statement_template_id',
                ],
                function (key) {
                    if (theme[key] <= 0 || !$scope.hasCustomTemplates) {
                        theme[key] = null;
                    }
                },
            );

            if (isExisting) {
                Theme.edit(
                    {
                        id: id,
                    },
                    theme,
                    function (theme) {
                        $scope.saving = false;
                        $scope.themeForm.$setPristine();
                        angular.extend($scope.theme, theme);

                        // give an artificial ID to help with ng-options
                        angular.forEach(
                            [
                                'credit_note_template_id',
                                'estimate_template_id',
                                'invoice_template_id',
                                'receipt_template_id',
                                'statement_template_id',
                            ],
                            function (key) {
                                if (!$scope.theme[key]) {
                                    $scope.theme[key] = -1;
                                }
                            },
                        );

                        Core.flashMessage('Your changes have been saved.', 'success');
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                theme.id = 'default';
                Theme.create(
                    {},
                    theme,
                    function (theme) {
                        $scope.saving = false;
                        $scope.themeForm.$setPristine();
                        isExisting = true;
                        angular.extend($scope.theme, theme);

                        // give an artificial ID to help with ng-options
                        angular.forEach(
                            [
                                'credit_note_template_id',
                                'estimate_template_id',
                                'invoice_template_id',
                                'receipt_template_id',
                                'statement_template_id',
                            ],
                            function (key) {
                                if (!$scope.theme[key]) {
                                    $scope.theme[key] = -1;
                                }
                            },
                        );

                        // make the new theme the company defualt
                        makeThemeDefault(theme);

                        Core.flashMessage('Your changes have been saved.', 'success');
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            }
        }

        function makeThemeDefault(theme) {
            // save to server
            $scope.savingDefault = true;
            $scope.error = null;

            Settings.editAccountsReceivable(
                {
                    default_theme_id: theme.id,
                },
                function () {
                    $scope.savingDefault = false;
                },
                function (result) {
                    $scope.savingDefault = false;
                    $scope.error = result.data;
                },
            );
        }

        function preview(type, theme, statementType) {
            // Hack to get angular $scope into the 'action' attribute fo form
            $('#themeForm').attr('action', InvoicedConfig.baseUrl + '/' + type + '/sample');

            // encode the theme as a JSON encoded object
            $('#themeObject').val(angular.toJson(theme));

            if (typeof statementType === 'string') {
                $('#previewStatementType').val(statementType);
            }

            $('#themeForm').submit();
        }
    }
})();
