(function () {
    'use strict';

    angular.module('app.settings').controller('AccountsPayableSettingsController', AccountsPayableSettingsController);

    AccountsPayableSettingsController.$inject = [
        '$scope',
        'selectedCompany',
        'Core',
        'AutoNumberSequence',
        'Settings',
        'Inbox',
    ];

    function AccountsPayableSettingsController($scope, selectedCompany, Core, AutoNumberSequence, Settings, Inbox) {
        $scope.company = angular.copy(selectedCompany);

        Core.setTitle('Accounts Payable Settings');

        let autoNumberTemplates = ['vendor', 'vendor_payment', 'vendor_payment_batch'];

        loadSettings();
        loadNextNumbers();

        $scope.saveAutoNumbering = saveAutoNumbering;
        $scope.saveAging = saveAging;
        $scope.updateAgingBuckets = updateAgingBuckets;
        $scope.cancelEditAging = cancelEditAging;

        function loadSettings() {
            $scope.loadingSettings = true;

            Settings.accountsPayable(function (settings) {
                $scope.settings = settings;

                // load A/P inbox
                Inbox.find(
                    { id: settings.inbox },
                    function (inbox) {
                        $scope.inbox = inbox;
                        $scope.loadingSettings = false;
                    },
                    function (result) {
                        $scope.loadingSettings = false;
                        Core.showMessage(result.data.message, 'error');
                    },
                );
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

            Settings.editAccountsPayable(
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
            Settings.accountsPayable(function (settings) {
                $scope.settings.aging_date = settings.aging_date;
                $scope.settings.aging_buckets = settings.aging_buckets;
            });
        }

        function loadNextNumbers() {
            angular.forEach(autoNumberTemplates, function (type) {
                $scope.loadingAutoNumbering++;
                AutoNumberSequence.get(
                    {
                        type: type,
                    },
                    function (result) {
                        $scope['next_' + type + '_number_full'] = result.next;
                        $scope.company['next_' + type + '_number'] = result.next_no;
                        parseAutoNumbering(type, result.template);
                        $scope.loadingAutoNumbering--;
                    },
                    function () {
                        $scope.loadingAutoNumbering--;
                    },
                );
            });
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
            $scope.saving = autoNumberTemplates.length;
            $scope.error = null;

            angular.forEach(autoNumberTemplates, function (type) {
                let sequence = buildSequence(type);
                let number = sequence.template.slice(0, sequence.template.length - 2) + sequence.next.toString();

                if ('vendor' === type && number.length > 32) {
                    $scope.error = { message: 'Vendor numbers cannot be more than 32 characters total.' };
                }
            });

            if ($scope.error !== null) {
                $scope.saving = 0;
                return;
            }

            // rebuild number templates
            angular.forEach(autoNumberTemplates, function (type) {
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
    }
})();
