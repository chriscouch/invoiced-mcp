(function () {
    'use strict';

    angular.module('app.settings').controller('EmailDeliverySettingsController', EmailDeliverySettingsController);

    EmailDeliverySettingsController.$inject = [
        '$scope',
        'Company',
        'selectedCompany',
        'Settings',
        'SmtpAccount',
        'Inbox',
        'Core',
        'Feature',
    ];

    function EmailDeliverySettingsController(
        $scope,
        Company,
        selectedCompany,
        Settings,
        SmtpAccount,
        Inbox,
        Core,
        Feature,
    ) {
        $scope.companyEmail = selectedCompany.email;
        $scope.hasFeature = Feature.hasFeature('email_whitelabel');
        $scope.loadingSettings = 0;

        $scope.testSmtp = function (host, port, username, password, encryption, authMode) {
            $scope.testResponse = false;
            $scope.testing = true;

            SmtpAccount.test(
                {
                    host: host,
                    port: port,
                    username: username,
                    password: password,
                    encryption: encryption,
                    auth_mode: authMode,
                },
                function () {
                    $scope.testing = false;
                    $scope.testResponse = 'Successfully connected to SMTP gateway!';
                    $scope.testResponseOk = true;
                },
                function (result) {
                    $scope.testing = false;
                    $scope.testResponse = result.data.message;
                    $scope.testResponseOk = false;
                },
            );
        };

        $scope.saveSettings = function (_settings, _smtpAccount, replyTo) {
            $scope.saving = 0;
            $scope.error = null;

            let params = {
                email_provider: _settings.email_provider,
                bcc: _settings.bcc,
                reply_to_inbox_id: replyTo === 'inbox' ? $scope.inbox.id : null,
            };

            if (params.email_provider === 'smtp') {
                $scope.saving++;

                let smtpParams = {
                    host: _smtpAccount.host,
                    port: _smtpAccount.port,
                    username: _smtpAccount.username,
                    encryption: _smtpAccount.encryption,
                    auth_mode: _smtpAccount.auth_mode,
                    fallback_on_failure: _smtpAccount.fallback_on_failure,
                };

                // only set password when given
                if (_smtpAccount.password) {
                    smtpParams.password = _smtpAccount.password;
                }

                SmtpAccount.update(
                    smtpParams,
                    function (smtpAccount) {
                        $scope.saving--;
                        angular.extend($scope.smtpAccount, smtpAccount);
                    },
                    function (result) {
                        $scope.saving--;
                        $scope.error = result.data;
                    },
                );
            }

            $scope.saving++;
            Settings.editAccountsReceivable(
                params,
                function (settings) {
                    Core.flashMessage('Your settings have been updated.', 'success');

                    $scope.saving--;
                    $scope.editSettings = false;
                    $scope.testResponse = false;
                    $scope.testResponseOk = false;

                    angular.extend($scope.settings, settings);
                },
                function (result) {
                    $scope.saving--;
                    $scope.error = result.data;
                },
            );
        };

        Core.setTitle('Email Delivery Settings');

        loadSettings();

        function loadSettings() {
            $scope.loadingSettings++;
            Settings.accountsReceivable(
                function (settings) {
                    $scope.loadingSettings--;
                    $scope.settings = settings;
                    $scope._settings = angular.copy(settings);
                    $scope.replyTo = settings.reply_to_inbox_id > 0 ? 'inbox' : 'company_email';
                },
                function (result) {
                    $scope.loadingSettings--;
                    Core.showMessage(result.data.message, 'error');
                },
            );

            $scope.loadingSettings++;
            SmtpAccount.get(
                function (smtpAccount) {
                    $scope.loadingSettings--;
                    $scope.smtpAccount = smtpAccount;
                    $scope._smtpAccount = angular.copy(smtpAccount);
                },
                function (result) {
                    $scope.loadingSettings--;

                    if (result.status == 404) {
                        $scope.smtpAccount = {
                            host: 'smtp.gmail.com',
                            port: 587,
                            encryption: 'tls',
                            auth_mode: 'login',
                            fallback_on_failure: true,
                        };
                        $scope._smtpAccount = angular.copy($scope.smtpAccount);
                    } else {
                        Core.showMessage(result.data.message, 'error');
                    }
                },
            );

            $scope.loadingSettings++;
            Settings.accountsReceivable(function (settings) {
                // load A/R inbox
                Inbox.find(
                    { id: settings.inbox },
                    function (inbox) {
                        $scope.loadingSettings--;
                        $scope.inbox = inbox;
                    },
                    function (result) {
                        $scope.loadingSettings--;
                        Core.showMessage(result.data.message, 'error');
                    },
                );
            });
        }
    }
})();
