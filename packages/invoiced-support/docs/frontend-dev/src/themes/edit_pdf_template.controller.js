(function () {
    'use strict';

    angular.module('app.themes').controller('EditPdfTemplateController', EditPdfTemplateController);

    EditPdfTemplateController.$inject = [
        '$scope',
        '$timeout',
        '$modalInstance',
        'PdfTemplate',
        'InvoicedConfig',
        'Core',
        'selectedCompany',
        'documentType',
        'pdfTemplate',
    ];

    function EditPdfTemplateController(
        $scope,
        $timeout,
        $modalInstance,
        PdfTemplate,
        InvoicedConfig,
        Core,
        selectedCompany,
        documentType,
        pdfTemplate,
    ) {
        if (pdfTemplate) {
            $scope.pdfTemplate = angular.copy(pdfTemplate);
        } else {
            $scope.pdfTemplate = {
                name: '',
                document_type: documentType,
                disable_smart_shrinking: false,
                template_engine: 'twig',
                html: InvoicedConfig.templates.pdf.twig[documentType],
                css: InvoicedConfig.templates.pdf.css[documentType],
                header_html: '',
                header_css: '',
                footer_html: '',
                footer_css: '',
                margin_left: '0.5cm',
                margin_right: '0.5cm',
                margin_top: '0.5cm',
                margin_bottom: '0.5cm',
            };
        }
        $scope.html = InvoicedConfig.templates.pdf.twig[documentType];
        $scope.company = selectedCompany;

        $scope.htmlCmOptions = {
            theme: 'monokai',
            lineNumbers: true,
            lineWrapping: true,
            indentWithTabs: true,
            tabSize: 2,
            matchBrackets: true,
            styleActiveLine: true,
            mode: {
                name: 'handlebars',
                base: 'text/html',
            },
            onLoad: function () {
                // force a CM UI refresh
                $scope.cmRefresh++;
            },
        };
        $scope.cssCmOptions = {
            theme: 'monokai',
            lineNumbers: true,
            lineWrapping: true,
            indentWithTabs: true,
            tabSize: 2,
            matchBrackets: true,
            styleActiveLine: true,
            mode: 'css',
            onLoad: function () {
                // force a CM UI refresh
                $scope.cmRefresh++;
            },
        };
        $scope.cmRefresh = 0;

        $timeout(function () {
            // force a CM UI refresh after the modal loads
            $scope.cmRefresh++;
        });

        $scope.save = saveTemplate;
        $scope.preview = preview;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        function saveTemplate(pdfTemplate) {
            if (pdfTemplate.id) {
                PdfTemplate.edit(
                    {
                        id: pdfTemplate.id,
                    },
                    {
                        name: pdfTemplate.name,
                        disable_smart_shrinking: pdfTemplate.disable_smart_shrinking,
                        html: pdfTemplate.html,
                        css: pdfTemplate.css,
                        header_html: pdfTemplate.header_html,
                        header_css: pdfTemplate.header_css,
                        footer_html: pdfTemplate.footer_html,
                        footer_css: pdfTemplate.footer_css,
                        margin_top: pdfTemplate.margin_top,
                        margin_bottom: pdfTemplate.margin_bottom,
                        margin_left: pdfTemplate.margin_left,
                        margin_right: pdfTemplate.margin_right,
                    },
                    function (_pdfTemplate) {
                        $scope.saving = false;
                        $modalInstance.close(_pdfTemplate);
                        Core.flashMessage('Your ' + _pdfTemplate.name + ' template has been saved.', 'success');
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                PdfTemplate.create(
                    pdfTemplate,
                    function (_pdfTemplate) {
                        $scope.saving = false;
                        $modalInstance.close(_pdfTemplate);
                        Core.flashMessage('Your ' + _pdfTemplate.name + ' template has been saved.', 'success');
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            }
        }

        function preview(pdfTemplate, statementType) {
            // Hack to get angular $scope into the 'action' attribute of form
            $('#pdfTemplateForm').attr('action', InvoicedConfig.baseUrl + '/' + pdfTemplate.document_type + 's/sample');

            // encode the template as JSON
            $('#pdfTemplateObject').val(angular.toJson(pdfTemplate));

            if (typeof statementType === 'string') {
                $('#pdfTemplateStatementType').val(statementType);
            }

            $('#pdfTemplateForm').submit();
        }
    }
})();
