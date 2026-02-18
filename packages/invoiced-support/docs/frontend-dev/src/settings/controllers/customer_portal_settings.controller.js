/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('CustomerPortalSettingsController', CustomerPortalSettingsController);

    CustomerPortalSettingsController.$inject = [
        '$scope',
        '$modal',
        '$window',
        'Company',
        'selectedCompany',
        'Core',
        'Settings',
        'Template',
        'CspTrustedSite',
    ];

    function CustomerPortalSettingsController(
        $scope,
        $modal,
        $window,
        Company,
        selectedCompany,
        Core,
        Settings,
        Template,
        CspTrustedSite,
    ) {
        $scope.company = angular.copy(selectedCompany);
        $scope.customerPortalDomain = selectedCompany.url.replace('http://', '').replace('https://', '');

        Core.setTitle('Customer Portal Settings');
        loadSettings();
        loadTemplates();
        loadCspTrustedSites();
        loadAttachments();

        $scope.setupCustomDomain = setupCustomDomain;
        $scope.editJavascript = editJavascript;
        $scope.editCss = editCss;
        $scope.saveSettings = saveSettings;
        $scope.addTrustedSite = addTrustedSite;
        $scope.editTrustedSite = editTrustedSite;
        $scope.deleteTrustedSite = deleteTrustedSite;
        $scope.attachments = [];

        function loadAttachments() {
            Company.attachments({}, function (attachments) {
                $scope.attachments = attachments;
            });
        }

        function setupCustomDomain() {
            const modalInstance = $modal.open({
                templateUrl: 'settings/views/setup-custom-domain.html',
                controller: 'SetupCustomDomainController',
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(
                function () {
                    // reload the page
                    $window.location.reload();
                },
                function () {
                    // canceled
                },
            );
        }

        function editJavascript(template) {
            const modalInstance = $modal.open({
                templateUrl: 'settings/views/edit-template.html',
                controller: 'EditTemplateSettingsController',
                size: 'lg',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    template: function () {
                        return template;
                    },
                    mode: function () {
                        return 'javascript';
                    },
                },
            });

            modalInstance.result.then(
                function (template) {
                    $scope.jsTemplate = template;
                },
                function () {
                    // canceled
                },
            );
        }

        function editCss(template) {
            const modalInstance = $modal.open({
                templateUrl: 'settings/views/edit-template.html',
                controller: 'EditTemplateSettingsController',
                size: 'lg',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    template: function () {
                        return template;
                    },
                    mode: function () {
                        return 'css';
                    },
                },
            });

            modalInstance.result.then(
                function (template) {
                    $scope.cssTemplate = template;
                },
                function () {
                    // canceled
                },
            );
        }

        function loadSettings() {
            $scope.loadingSettings = true;

            Settings.customerPortal(function (settings) {
                $scope.settings = settings;
                $scope.loadingSettings = false;
            });
        }

        function saveSettings(params) {
            $scope.saving = true;
            $scope.error = null;

            Settings.editCustomerPortal(
                {
                    allow_advance_payments: params.allow_advance_payments,
                    allow_autopay_enrollment: params.allow_autopay_enrollment,
                    allow_billing_portal_cancellations: params.allow_billing_portal_cancellations,
                    allow_billing_portal_profile_changes: params.allow_billing_portal_profile_changes,
                    allow_editing_contacts: params.allow_editing_contacts,
                    allow_invoice_payment_selector: params.allow_invoice_payment_selector,
                    allow_partial_payments: params.allow_partial_payments,
                    customer_portal_auth_url: params.customer_portal_auth_url,
                    enabled: params.enabled,
                    google_analytics_id: params.google_analytics_id,
                    include_sub_customers: params.include_sub_customers,
                    invoice_payment_to_item_selection: params.invoice_payment_to_item_selection,
                    require_authentication: params.require_authentication,
                    show_powered_by: params.show_powered_by,
                    welcome_message: params.welcome_message,
                },
                function (settings) {
                    Core.flashMessage('Your settings have been updated.', 'success');

                    $scope.saving = false;
                    $scope.editDefaults = false;

                    angular.extend($scope.settings, settings);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function loadTemplates() {
            $scope.loading = true;
            $scope.jsTemplate = {};
            $scope.cssTemplate = {};

            Template.findAll(
                { paginate: 'none' },
                function (templates) {
                    angular.forEach(templates, function (template) {
                        if (template.filename === 'billing_portal/index.js') {
                            $scope.jsTemplate = template;
                        } else if (template.filename === 'billing_portal/styles.css') {
                            $scope.cssTemplate = template;
                        }
                    });

                    $scope.loading = false;
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadCspTrustedSites() {
            $scope.trustedSites = [];

            CspTrustedSite.findAll(
                { paginate: 'none' },
                function (trustedSites) {
                    $scope.trustedSites = trustedSites;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function addTrustedSite() {
            const modalInstance = $modal.open({
                templateUrl: 'settings/views/csp-trusted-site.html',
                controller: 'CspTrustedSiteController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    trustedSite: function () {
                        return null;
                    },
                },
            });

            modalInstance.result.then(
                function (trustedSite) {
                    $scope.trustedSites.push(trustedSite);
                },
                function () {
                    // canceled
                },
            );
        }

        function editTrustedSite(trustedSite) {
            const modalInstance = $modal.open({
                templateUrl: 'settings/views/csp-trusted-site.html',
                controller: 'CspTrustedSiteController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    trustedSite: function () {
                        return trustedSite;
                    },
                },
            });

            modalInstance.result.then(
                function (_trustedSite) {
                    angular.extend(trustedSite, _trustedSite);
                },
                function () {
                    // canceled
                },
            );
        }

        function deleteTrustedSite(trustedSite, $index) {
            vex.dialog.confirm({
                message: 'Are you sure you want to remove this CSP Trusted Site?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting = true;
                        CspTrustedSite.delete(
                            {
                                id: trustedSite.id,
                            },
                            function () {
                                $scope.deleting = false;
                                $scope.trustedSites.splice($index, 1);
                            },
                            function (result) {
                                $scope.deleting = false;
                                Core.showMessage(result.data.message, 'danger');
                            },
                        );
                    }
                },
            });
        }
    }
})();
