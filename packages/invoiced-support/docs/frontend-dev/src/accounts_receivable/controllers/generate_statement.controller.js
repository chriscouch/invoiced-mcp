/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('GenerateStatementController', GenerateStatementController);

    GenerateStatementController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        '$timeout',
        'Customer',
        'Core',
        'Permission',
        'selectedCompany',
        'customer',
        'DatePickerService',
    ];

    function GenerateStatementController(
        $scope,
        $modal,
        $modalInstance,
        $timeout,
        Customer,
        Core,
        Permission,
        selectedCompany,
        customer,
        DatePickerService,
    ) {
        $scope.customer = customer;
        $scope.type = 'open_item';
        $scope.currency = selectedCompany.currency;
        $scope.statementDate = new Date();
        $scope.openItemMode = 'open';

        $scope.period = {
            period: 'this_month',
        };

        $scope.dateOptions = DatePickerService.getOptions();

        $scope.hasSendingPermissions = Permission.hasSomePermissions([
            'text_messages.send',
            'letters.send',
            'emails.send',
        ]);

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
            // this is needed to ensure the datepicker
            // can be opened again
            $timeout(function () {
                $scope[name] = false;
            });
        };

        $scope.clientView = function (type, currency, period, statementDate, openItemMode) {
            $modalInstance.dismiss('cancel');

            $modal.open({
                templateUrl: 'components/views/url-modal.html',
                controller: 'URLModalController',
                resolve: {
                    url: function () {
                        return clientViewUrl(type, currency, period, statementDate, openItemMode);
                    },
                    customer: function () {
                        return null;
                    },
                },
            });
        };

        $scope.download = function (type, currency, period, statementDate, openItemMode) {
            $modalInstance.dismiss('cancel');
            window.location = statementPdfUrl(type, currency, period, statementDate, openItemMode);
        };

        $scope.send = function (customer, type, currency, period, statementDate, openItemMode) {
            $('.generate-statement-modal').hide();

            const modalInstance = $modal.open({
                templateUrl: 'sending/views/send-document.html',
                controller: 'SendDocumentController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                windowClass: 'send-document-modal',
                resolve: {
                    model: function () {
                        return Customer;
                    },
                    paymentPlan: function () {
                        return null;
                    },
                    _document: function () {
                        return customer;
                    },
                    customerId: function () {
                        return customer.id;
                    },
                    sendOptions: function () {
                        return buildOptions(type, currency, period, statementDate, openItemMode);
                    },
                },
            });

            modalInstance.result.then(
                function (result) {
                    $modalInstance.dismiss('cancel');
                    Core.flashMessage(result, 'success');
                },
                function () {
                    // canceled
                    $('.generate-statement-modal').show();
                },
            );
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function buildOptions(type, currency, period, statementDate, openItemMode) {
            let options = {
                type: type,
                currency: currency,
            };

            if (type === 'balance_forward') {
                options.start = moment(period.start).startOf('day').unix();
                options.end = moment(period.end).endOf('day').unix();
            } else if (type === 'open_item') {
                options.end = moment(statementDate).endOf('day').unix();
                options.items = openItemMode;
            }

            return options;
        }

        function clientViewUrl(type, currency, period, statementDate, openItemMode) {
            let options = buildOptions(type, currency, period, statementDate, openItemMode);
            let fragments = [];
            angular.forEach(options, function (value, key) {
                fragments.push(key + '=' + value);
            });

            return $scope.customer.statement_url + '?' + fragments.join('&');
        }

        function statementPdfUrl(type, currency, period, statementDate, openItemMode) {
            let options = buildOptions(type, currency, period, statementDate, openItemMode);
            let fragments = ['t=0'];
            angular.forEach(options, function (value, key) {
                fragments.push(key + '=' + value);
            });

            return $scope.customer.statement_pdf_url + '?' + fragments.join('&');
        }
    }
})();
