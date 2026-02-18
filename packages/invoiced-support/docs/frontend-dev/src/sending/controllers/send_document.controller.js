(function () {
    'use strict';

    angular.module('app.sending').controller('SendDocumentController', SendDocumentController);

    SendDocumentController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        'Core',
        'Customer',
        'selectedCompany',
        'model',
        'customerId',
        '_document',
        'sendOptions',
        'Permission',
        'Feature',
        'paymentPlan',
    ];

    function SendDocumentController(
        $scope,
        $modalInstance,
        $modal,
        Core,
        Customer,
        selectedCompany,
        model,
        customerId,
        _document,
        sendOptions,
        Permission,
        Feature,
        paymentPlan,
    ) {
        $scope.loading = 0;
        $scope.choices = [];
        $scope.send = send;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        loadCustomer(customerId);

        function loadCustomer(id) {
            $scope.loading++;
            Customer.find(
                {
                    id: id,
                },
                {
                    include: 'address',
                },
                function (customer) {
                    $scope.customer = customer;
                    $scope.loading--;

                    determineChoices(customer);
                    if ($scope.choices.length === 1) {
                        send($scope.choices[0].id);
                    }
                },
            );
        }

        function determineChoices() {
            $scope.choices = [];

            if (Feature.hasFeature('email_sending') && Permission.hasPermission('emails.send')) {
                $scope.choices.push({
                    id: 'email',
                    name: 'Email',
                    icon: 'fa-envelope-open-text',
                });
            }

            //only email allowed for send sign in url
            if (sendOptions.url) {
                return;
            }

            if (
                Feature.hasFeature('letters') &&
                Permission.hasPermission('letters.send') &&
                (_document.object === 'customer' || _document.object === 'invoice')
            ) {
                $scope.choices.push({
                    id: 'letter',
                    name: 'Letter',
                    icon: 'fa-mailbox',
                });
            }

            if (
                Feature.hasFeature('sms') &&
                Permission.hasPermission('text_messages.send') &&
                (_document.object === 'customer' || _document.object === 'invoice')
            ) {
                $scope.choices.push({
                    id: 'sms',
                    name: 'Text Message',
                    icon: 'fa-sms',
                });
            }
        }

        function send(type) {
            if (type === 'email') {
                sendEmail();
            } else if (type === 'invoiced') {
                sendInvoiced();
            } else if (type === 'letter') {
                sendLetter();
            } else if (type === 'sms') {
                sendSms();
            }
        }

        function sendEmail() {
            $('.send-document-modal').hide();

            const modalInstance = $modal.open({
                templateUrl: 'sending/views/send-document-email.html',
                controller: 'NewEmailController',
                size: 'lg',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    options: determineSendOptions,
                },
            });

            modalInstance.result.then(function (result) {
                $modalInstance.close(result);
            }, handleCanceled);
        }

        function sendInvoiced() {
            $('.send-document-modal').hide();

            const modalInstance = $modal.open({
                templateUrl: 'sending/views/send-document-network.html',
                controller: 'SendDocumentNetworkController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    model: function () {
                        return model;
                    },
                    documentId: function () {
                        return _document.id;
                    },
                    customer: function () {
                        return $scope.customer;
                    },
                    alreadySent: function () {
                        return !!_document.network_document;
                    },
                    options: function () {
                        return sendOptions;
                    },
                },
            });

            modalInstance.result.then(function (result) {
                $modalInstance.close(result);
            }, handleCanceled);
        }

        function sendLetter() {
            $('.send-document-modal').hide();

            const modalInstance = $modal.open({
                templateUrl: 'sending/views/send-document-letter.html',
                controller: 'NewLetterController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    model: function () {
                        return model;
                    },
                    documentId: function () {
                        return _document.id;
                    },
                    customer: function () {
                        return $scope.customer;
                    },
                    options: function () {
                        return sendOptions;
                    },
                },
            });

            modalInstance.result.then(function (result) {
                $modalInstance.close(result);
            }, handleCanceled);
        }

        function sendSms() {
            $('.send-document-modal').hide();

            const modalInstance = $modal.open({
                templateUrl: 'sending/views/send-document-sms.html',
                controller: 'NewTextMessageController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    model: function () {
                        return model;
                    },
                    documentId: function () {
                        return _document.id;
                    },
                    customer: function () {
                        return $scope.customer;
                    },
                },
            });

            modalInstance.result.then(function (result) {
                $modalInstance.close(result);
            }, handleCanceled);
        }

        function determineSendOptions() {
            let options = {
                model: model,
                id: _document.id,
                customer: $scope.customer,
                options: sendOptions,
            };

            if (_document.object === 'credit_note') {
                options.type = 'credit_note';

                angular.extend(options, {
                    title: 'Credit Note',
                    template: 'credit_note_email',
                });
            } else if (_document.object === 'customer') {
                if (typeof sendOptions.url === 'string') {
                    options.type = 'sign_in';
                    angular.extend(options, {
                        subject: 'Sign in to our customer portal',
                        message:
                            'Hello,\n\nPlease sign in to our customer portal here:\n\n' +
                            '{{ sign_in_button | raw }}' +
                            '\n\nThis sign in link will expire in 1 day.',
                    });
                    delete options.url;
                    delete sendOptions.url;
                } else {
                    options.type = 'statement';

                    angular.extend(options, {
                        title: 'Statement',
                        template: 'statement_email',
                    });
                }
            } else if (_document.object === 'estimate') {
                options.type = 'estimate';

                angular.extend(options, {
                    title: 'Estimate',
                    template: 'estimate_email',
                });
            } else if (_document.object === 'invoice') {
                options.type = 'invoice';
                if (_document.paid) {
                    angular.extend(options, {
                        title: 'Invoice',
                        templateName: 'Thank You',
                        template: 'paid_invoice_email',
                    });
                } else if (_document.status === 'past_due') {
                    angular.extend(options, {
                        title: 'Invoice',
                        templateName: 'Past Due Invoice',
                        template: 'late_payment_reminder_email',
                    });
                } else if (_document.payment_plan && paymentPlan && paymentPlan.status === 'pending_signup') {
                    options.type = 'payment_plan';

                    angular.extend(options, {
                        title: 'Invoice',
                        templateName: 'Payment Plan',
                        template: 'payment_plan_onboard_email',
                    });
                } else if (_document.status === 'sent' || _document.status === 'viewed') {
                    angular.extend(options, {
                        title: 'Invoice',
                        templateName: 'Invoice Reminder',
                        template: 'unpaid_invoice_email',
                    });
                } else {
                    angular.extend(options, {
                        title: 'Invoice',
                        templateName: 'New Invoice',
                        template: 'new_invoice_email',
                    });
                }
            } else if (_document.object === 'payment' || _document.object === 'transaction') {
                options.type = 'transaction';

                angular.extend(options, {
                    title: 'Payment Receipt',
                    template: 'payment_receipt_email',
                });
            }

            return options;
        }

        function handleCanceled() {
            $('.send-document-modal').show();

            if ($scope.choices.length === 1) {
                $scope.close();
            }
        }
    }
})();
