(function () {
    'use strict';

    angular
        .module('app.settings')
        .controller('AccountsReceivableSettingsController', AccountsReceivableSettingsController);

    AccountsReceivableSettingsController.$inject = [
        '$scope',
        'selectedCompany',
        'Core',
        'AutoNumberSequence',
        'Settings',
        'Feature',
    ];

    function AccountsReceivableSettingsController(
        $scope,
        selectedCompany,
        Core,
        AutoNumberSequence,
        Settings,
        Feature,
    ) {
        $scope.company = angular.copy(selectedCompany);

        Core.setTitle('Accounts Receivable Settings');

        loadSettings();
        loadNextNumbers();
        loadFeatures();

        $scope.saveSettings = saveSettings;
        $scope.saveAutoNumbering = saveAutoNumbering;
        $scope.saveSetting = saveSetting;
        $scope.cancelEditDefaults = cancelEditDefaults;
        $scope.toggleFeature = toggleFeature;
        $scope.saveAutoPay = saveAutoPay;
        $scope.cancelEditAutoPay = cancelEditAutoPay;
        $scope.saveAging = saveAging;
        $scope.updateAgingBuckets = updateAgingBuckets;
        $scope.cancelEditAging = cancelEditAging;

        $scope.autoPayOptions = [];
        for (let i = 0; i <= 45; i++) {
            $scope.autoPayOptions.push({
                amount: i,
                name: i == 1 ? '+1 day' : '+' + i + ' days',
            });
        }

        $scope.paymentRetryOptions = [];
        for (let j = 1; j <= 10; j++) {
            $scope.paymentRetryOptions.push({
                amount: j,
                name: j == 1 ? '1 day' : j + ' days',
            });
        }

        function loadSettings() {
            $scope.loadingSettings = true;

            Settings.accountsReceivable(function (settings) {
                $scope.settings = settings;
                $scope.loadingSettings = false;
                $scope.autoPay = settings.default_collection_mode === 'auto';
                if (!$scope.hasAutoPay && $scope.autoPay) {
                    $scope.hasAutoPay = true;
                }
            });
        }

        function saveSettings(params) {
            $scope.saving = true;
            $scope.error = null;

            Settings.editAccountsReceivable(
                {
                    default_customer_type: params.default_customer_type,
                    chase_new_invoices: params.chase_new_invoices,
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

        function cancelEditDefaults() {
            $scope.editDefaults = false;
            $scope.error = false;
            Settings.accountsReceivable(function (settings) {
                $scope.settings.default_customer_type = settings.default_customer_type;
                $scope.settings.chase_new_invoices = settings.chase_new_invoices;
            });
        }

        function saveAutoPay(params) {
            $scope.saving = true;
            $scope.error = null;

            params.default_collection_mode = $scope.autoPay ? 'auto' : 'manual';

            Settings.editAccountsReceivable(
                {
                    default_collection_mode: params.default_collection_mode,
                    autopay_delay_days: params.autopay_delay_days,
                    payment_retry_schedule: params.payment_retry_schedule,
                },
                function (settings) {
                    Core.flashMessage('Your settings have been updated.', 'success');

                    $scope.saving = false;
                    $scope.editAutoPay = false;

                    angular.extend($scope.settings, settings);
                    $scope.autoPay = settings.default_collection_mode === 'auto';
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function cancelEditAutoPay() {
            $scope.editAutoPay = false;
            $scope.error = false;
            Settings.accountsReceivable(function (settings) {
                $scope.settings.autopay_delay_days = settings.autopay_delay_days;
                $scope.settings.payment_retry_schedule = settings.payment_retry_schedule;
            });
        }

        function saveAging(params) {
            $scope.saving = true;
            $scope.error = null;
            if (params.aging_date === 'due_date' && params.aging_buckets[0] !== -1) {
                params.aging_buckets.unshift(-1);
            }

            if (params.aging_date === 'date' && params.aging_buckets[0] === -1) {
                params.aging_buckets.shift();
            }

            if (params.aging_buckets.length < 4) {
                $scope.error = 'You cannot have less than 4 ranges.';
                $scope.saving = false;
                return;
            }

            if (params.aging_buckets.length > 6) {
                $scope.error = 'You cannot have more than 6 ranges.';
                $scope.saving = false;
                return;
            }

            for (let i = 1; i < params.aging_buckets.length; i++) {
                if (params.aging_buckets[i] <= params.aging_buckets[i - 1]) {
                    $scope.error = 'Each range must be greater than the previous one.';
                    $scope.saving = false;
                    return;
                }
            }

            Settings.editAccountsReceivable(
                {
                    aging_date: params.aging_date,
                    aging_buckets: params.aging_buckets,
                },
                function (settings) {
                    Core.flashMessage('Your settings have been updated.', 'success');

                    $scope.saving = false;
                    $scope.editAging = false;

                    angular.extend($scope.settings, settings);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function updateAgingBuckets(params) {
            $scope.error = null;

            if (params.aging_date === 'due_date' && params.aging_buckets[0] !== -1) {
                params.aging_buckets.unshift(-1);
            }
            if (params.aging_date === 'date' && params.aging_buckets[0] === -1) {
                params.aging_buckets.shift();
            }

            if (params.aging_buckets.length < 4) {
                params.aging_buckets.push(params.aging_buckets[params.aging_buckets.length - 1] + 1);
            }

            if (params.aging_buckets.length > 6) {
                $scope.error = 'You cannot have more than 6 ranges.';
                return;
            }

            for (let i = 1; i < params.aging_buckets.length; i++) {
                if (params.aging_buckets[i] <= params.aging_buckets[i - 1]) {
                    $scope.error = 'The beginning of each aging range must be greater than the previous range.';
                    return;
                }
            }
        }

        function cancelEditAging() {
            $scope.editAging = false;
            $scope.error = false;
            Settings.accountsReceivable(function (settings) {
                $scope.settings.aging_date = settings.aging_date;
                $scope.settings.aging_buckets = settings.aging_buckets;
            });
        }

        function loadNextNumbers() {
            $scope.loadingAutoNumbering = 4;

            AutoNumberSequence.get(
                {
                    type: 'customer',
                },
                function (result) {
                    $scope.next_customer_number_full = result.next;
                    $scope.company.next_customer_number = result.next_no;
                    parseAutoNumbering('customer', result.template);
                    $scope.loadingAutoNumbering--;
                },
                function () {
                    $scope.loadingAutoNumbering--;
                },
            );

            AutoNumberSequence.get(
                {
                    type: 'invoice',
                },
                function (result) {
                    $scope.next_invoice_number_full = result.next;
                    $scope.company.next_invoice_number = result.next_no;
                    parseAutoNumbering('invoice', result.template);
                    $scope.loadingAutoNumbering--;
                },
                function () {
                    $scope.loadingAutoNumbering--;
                },
            );

            AutoNumberSequence.get(
                {
                    type: 'estimate',
                },
                function (result) {
                    $scope.next_estimate_number_full = result.next;
                    $scope.company.next_estimate_number = result.next_no;
                    parseAutoNumbering('estimate', result.template);
                    $scope.loadingAutoNumbering--;
                },
                function () {
                    $scope.loadingAutoNumbering--;
                },
            );

            AutoNumberSequence.get(
                {
                    type: 'credit_note',
                },
                function (result) {
                    $scope.next_credit_note_number_full = result.next;
                    $scope.company.next_credit_note_number = result.next_no;
                    parseAutoNumbering('credit_note', result.template);
                    $scope.loadingAutoNumbering--;
                },
                function () {
                    $scope.loadingAutoNumbering--;
                },
            );
        }

        function pad(n, width, z) {
            z = z || '0';
            n = n + '';
            return n.length >= width ? n : new Array(width - n.length + 1).join(z) + n;
        }

        function parseAutoNumbering(type, template) {
            // parse auto-numbering templates
            let prefix = '';
            let width = 4;

            // handle templates of the form, i.e. %04d
            if (template.indexOf('%') !== -1) {
                prefix = template.substr(0, template.indexOf('%'));
                let percentStr = template.substr(template.indexOf('%'));

                if (percentStr === '%d') {
                    width = 0;
                } else {
                    width = parseInt(percentStr.replace('%0', '').replace('d', ''));
                }
            } else {
                prefix = template;
            }

            $scope[type + '_no_prefix'] = prefix;

            // next numbers padded with 0s
            $scope['next_' + type + '_number'] = pad($scope.company['next_' + type + '_number'], width);
        }

        function saveAutoNumbering() {
            let templates = ['customer', 'invoice', 'estimate', 'credit_note'];
            $scope.saving = templates.length;
            $scope.error = null;

            angular.forEach(templates, function (type) {
                let sequence = buildSequence(type);
                let number = sequence.template.slice(0, sequence.template.length - 2) + sequence.next.toString();

                if ('customer' === type && number.length > 32) {
                    $scope.error = { message: 'Account numbers cannot be more than 32 characters total.' };
                } else if ('estimate' === type && number.length > 15) {
                    $scope.error = { message: 'Estimate numbers cannot be more than 15 characters total.' };
                } else if ('invoice' === type && number.length > 15) {
                    $scope.error = { message: 'Invoice numbers cannot be more than 15 characters total.' };
                } else if ('credit_note' === type && number.length > 15) {
                    $scope.error = { message: 'Credit note numbers cannot be more than 15 characters total.' };
                }
            });

            if ($scope.error !== null) {
                $scope.saving = 0;
                return;
            }

            // rebuild number templates
            angular.forEach(templates, function (type) {
                let sequence = buildSequence(type);

                AutoNumberSequence.edit(
                    {
                        type: type,
                    },
                    sequence,
                    function () {
                        $scope.saving--;

                        if (!$scope.saving) {
                            Core.flashMessage('The automatic numbering sequences have been updated.', 'success');

                            $scope.editAutoNumbering = false;

                            $scope.autoNumberingForm.$setPristine();

                            loadNextNumbers();
                        }
                    },
                    function (result) {
                        $scope.saving--;
                        $scope.error = result.data;
                    },
                );
            });
        }

        function buildSequence(type) {
            let next = $scope['next_' + type + '_number'];

            let sequence = {
                next: parseInt(next),
            };

            // determine width
            let width = 0;
            if (next.substr(0, 1) === '0') {
                width = Math.min(10, next.length);
            }

            // build template
            let prefix = $scope[type + '_no_prefix'];
            if (width > 0) {
                sequence.template = prefix + '%0' + width + 'd';
            } else {
                sequence.template = prefix + '%d';
            }

            return sequence;
        }

        function saveSetting(setting, value) {
            $scope.saving = true;
            $scope.error = null;

            let params = {};
            params[setting] = value;

            Settings.editAccountsReceivable(
                params,
                function (settings) {
                    $scope.saving = false;

                    Core.flashMessage('Your settings have been updated.', 'success');

                    angular.extend($scope.settings, settings);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function loadFeatures() {
            $scope.features = {
                estimates: Feature.hasFeature('estimates'),
                subscriptions: Feature.hasFeature('subscriptions'),
            };
        }

        function toggleFeature(feature, enabled) {
            $scope.saving = true;
            $scope.error = null;

            Feature.edit(
                {
                    id: feature,
                },
                {
                    enabled: enabled,
                },
                function () {
                    $scope.saving = false;

                    Core.flashMessage('Your settings have been updated.', 'success');

                    $scope.features[feature] = enabled;
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
