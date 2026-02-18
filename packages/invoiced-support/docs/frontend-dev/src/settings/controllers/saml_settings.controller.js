(function () {
    'use strict';

    angular.module('app.settings').controller('SamlSettingsController', SamlSettingsController);

    SamlSettingsController.$inject = ['$scope', 'Core', 'InvoicedConfig', 'Settings', '$translate'];

    function SamlSettingsController($scope, Core, InvoicedConfig, Settings, $translate) {
        Core.setTitle('Single Sign-On Settings');

        $scope.loading = true;
        $scope.acsUrl = InvoicedConfig.ssoAcsUrl;
        $scope.entityId = InvoicedConfig.ssoEntityId;
        $scope.loginUrlBase = InvoicedConfig.baseUrl;

        Settings.saml(
            function (settings) {
                settings.enabled = Boolean(settings.enabled);
                delete settings.created_at;
                delete settings.updated_at;
                delete settings.company;
                delete settings.company_id;
                $scope.settings = settings;
                $scope.loading = false;
            },
            function (result) {
                $scope.loading = false;
                Core.showMessage(result.data.message, 'error');
            },
        );

        $scope.save = function () {
            $scope.saving = true;
            Settings.editSaml(
                {},
                $scope.settings,
                function () {
                    $scope.saving = false;
                    Core.flashMessage($translate.instant('settings.members.saml.success'), 'success');
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };
    }
})();
