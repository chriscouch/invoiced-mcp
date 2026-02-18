(function () {
    'use strict';

    angular.module('app.events').directive('eventMessage', eventMessage);

    function eventMessage() {
        return {
            restrict: 'A',
            templateUrl: 'events/views/event-message.html',
            scope: {
                event: '=',
            },
            controller: [
                '$scope',
                '$modal',
                '$window',
                '$filter',
                'selectedCompany',
                'Money',
                'ObjectDeepLink',
                function ($scope, $modal, $window, $filter, selectedCompany, Money, ObjectDeepLink) {
                    // build user information
                    if ($scope.event.user) {
                        if ($scope.event.user.id > 0) {
                            $scope.name = $.trim($scope.event.user.first_name + ' ' + $scope.event.user.last_name);
                        } else if ($scope.event.user.id == -3) {
                            $scope.name = 'API';
                            $scope.fromApi = true;
                        } else if ($scope.event.user.id == -2) {
                            $scope.name = 'Invoiced';
                            $scope.fromInvoiced = true;
                        } else if ($scope.event.user.id == -1) {
                            $scope.name = 'Customer';
                            $scope.fromCustomer = true;
                        }
                    }

                    $scope.avatarOptions = {
                        height: 35,
                        width: 35,
                    };

                    $scope.eventType = $scope.event.type.split('.')[0];
                    $scope.operationType = operationType();
                    $scope.isNegative = isNegative($scope.event);
                    $scope.isPositive = isPositive($scope.event);
                    $scope.href = buildLink($scope.event);

                    $scope.message = function (event) {
                        if (event == 'undefined') {
                            return '';
                        }

                        let m = '';

                        // A message is composed of attributed parts. By
                        // parsing and concatenating those parts together
                        // we can produce a unified message.
                        angular.forEach(event.message, function (part) {
                            if (!part) {
                                return;
                            }

                            if (typeof part.parsed_value !== 'undefined') {
                                m += part.parsed_value;
                                return;
                            }

                            if (part.value == null) {
                                part.value = '';
                            }

                            if (typeof part.value == 'object') {
                                if (part.value.type == 'money') {
                                    let company = selectedCompany;
                                    part.value = Money.currencyFormat(
                                        part.value.amount,
                                        part.value.currency,
                                        company.moneyFormat,
                                        true,
                                    );
                                }
                            } else {
                                part.value = $filter('escapeHtml')(part.value);
                            }

                            let val = '';
                            if (part.value == '(deleted)') {
                                val += '<strong>(deleted)</strong>';
                                return;
                            }

                            if (part.type == 'string') {
                                val += part.value;
                            } else {
                                val += '<strong>' + part.value + '</strong>';
                            }

                            // only compile message parts once
                            part.parsed_value = val;
                            m += val;
                        });

                        return m;
                    };

                    $scope.go = function (event, $e) {
                        if (event.type.indexOf('email') === 0) {
                            $modal.open({
                                templateUrl: 'sending/views/email-details.html',
                                controller: 'EmailDetailsController',
                                resolve: {
                                    event: function () {
                                        return event;
                                    },
                                },
                            });

                            $e.preventDefault();
                        } else if (event.type.indexOf('letter') === 0) {
                            $modal.open({
                                templateUrl: 'sending/views/letter-details.html',
                                controller: 'LetterDetailsController',
                                resolve: {
                                    event: function () {
                                        return event;
                                    },
                                },
                            });

                            $e.preventDefault();
                        } else if (event.type.indexOf('text_message') === 0) {
                            $modal.open({
                                templateUrl: 'sending/views/text-message-details.html',
                                controller: 'TextMessageDetailsController',
                                resolve: {
                                    event: function () {
                                        return event;
                                    },
                                },
                            });

                            $e.preventDefault();
                        } else if (event.type.indexOf('.viewed') !== -1) {
                            $modal.open({
                                templateUrl: 'accounts_receivable/views/view-details.html',
                                controller: 'ViewDetailsController',
                                resolve: {
                                    event: function () {
                                        return event;
                                    },
                                },
                                size: 'sm',
                            });

                            $e.preventDefault();
                        }
                    };

                    function isNegative(event) {
                        // otherwise, these event types are considered negative
                        return (
                            event.type === 'customer.deleted' ||
                            event.type === 'invoice.deleted' ||
                            event.type === 'invoice.payment_failed' ||
                            event.type === 'estimate.deleted' ||
                            event.type === 'subscription.deleted' ||
                            event.type === 'payment.deleted' ||
                            event.type === 'transaction.deleted' ||
                            event.type === 'credit_note.deleted' ||
                            event.type === 'email.not_sent' ||
                            event.type === 'refund.created' ||
                            event.type === 'charge.failed'
                        );
                    }

                    function isPositive(event) {
                        return event.type === 'invoice.paid';
                    }

                    function buildLink(event) {
                        if (event.type.indexOf('.viewed') !== -1) {
                            return '#';
                        }

                        let objectType = event.type.split('.')[0];
                        let objectId = event.data.object.id;

                        let customerId, estimateId, invoiceId, creditNoteId, paymentId;

                        if (objectType === 'customer' && event.type.indexOf('.deleted') !== -1) {
                            return null;
                        }

                        if (objectType === 'email' || objectType === 'letter' || objectType === 'text_message') {
                            return '#';
                        }

                        if (event.type === 'estimate.commented') {
                            angular.forEach(event.message, function (part) {
                                if (part.type === 'estimate') {
                                    estimateId = part.id;
                                }
                            });

                            if (estimateId) {
                                return '/estimates/' + estimateId + '/conversation';
                            }
                        }

                        if (objectType === 'estimate' && event.type.indexOf('.deleted') !== -1) {
                            return null;
                        }

                        if (event.type === 'invoice.commented') {
                            angular.forEach(event.message, function (part) {
                                if (part.type === 'invoice') {
                                    invoiceId = part.id;
                                }
                            });

                            if (invoiceId) {
                                return '/invoices/' + invoiceId + '/conversation';
                            }
                        }

                        if (event.type === 'invoice.payment_expected' || event.type === 'promise_to_pay.broken') {
                            angular.forEach(event.message, function (part) {
                                if (part.type === 'invoice') {
                                    invoiceId = part.id;
                                }
                            });

                            if (invoiceId) {
                                return '/invoices/' + invoiceId;
                            }
                        }

                        if (objectType === 'invoice' && event.type.indexOf('.deleted') !== -1) {
                            return null;
                        }

                        if (event.type === 'credit_note.commented') {
                            angular.forEach(event.message, function (part) {
                                if (part.type === 'credit_note') {
                                    creditNoteId = part.id;
                                }
                            });

                            if (creditNoteId) {
                                return '/credit_notes/' + creditNoteId + '/conversation';
                            }
                        }

                        if (objectType === 'credit_note' && event.type.indexOf('.deleted') !== -1) {
                            return null;
                        }

                        if (objectType === 'note' || objectType === 'task') {
                            angular.forEach(event.message, function (part) {
                                if (part.type === 'customer') {
                                    customerId = part.id;
                                }
                            });

                            if (customerId) {
                                return '/customers/' + customerId + '/collections';
                            }
                        }

                        if (objectType === 'contact' || objectType === 'payment_source') {
                            angular.forEach(event.message, function (part) {
                                if (part.type === 'customer') {
                                    customerId = part.id;
                                }
                            });

                            if (customerId) {
                                return '/customers/' + customerId;
                            }
                        }

                        if (objectType === 'refund') {
                            angular.forEach(event.message, function (part) {
                                if (part.type === 'payment') {
                                    paymentId = part.id;
                                }
                            });

                            if (paymentId) {
                                return '/payments/' + paymentId;
                            }
                        }

                        if (objectType === 'charge') {
                            angular.forEach(event.message, function (part) {
                                if (part.type === 'payment') {
                                    paymentId = part.id;
                                }
                            });

                            if (paymentId) {
                                return '/payments/' + paymentId;
                            }
                        }

                        if (objectType === 'transaction' && event.type.indexOf('.deleted') !== -1) {
                            return null;
                        }

                        if (objectType === 'line_item') {
                            angular.forEach(event.message, function (part) {
                                if (part.type === 'customer') {
                                    customerId = part.id;
                                }
                            });

                            if (customerId) {
                                return '/customers/' + customerId;
                            }
                        }

                        if (objectType === 'payment_plan') {
                            angular.forEach(event.message, function (part) {
                                if (part.type === 'invoice') {
                                    invoiceId = part.id;
                                }
                            });

                            if (invoiceId) {
                                return '/invoices/' + invoiceId;
                            }
                        }

                        return ObjectDeepLink.getUrl(objectType, objectId);
                    }

                    function operationType() {
                        let parts = $scope.event.type.split('.');
                        let operation = parts[parts.length - 1];
                        if (operation === 'created') {
                            return 'create';
                        } else if (operation === 'updated' || operation === 'status_updated') {
                            return 'edit';
                        } else if (operation === 'viewed') {
                            return 'view';
                        } else if (
                            operation === 'completed' ||
                            operation === 'finished' ||
                            operation === 'paid' ||
                            operation === 'sent' ||
                            operation === 'received'
                        ) {
                            return 'succeeded';
                        } else if (operation === 'failed') {
                            return 'failed';
                        } else if (operation === 'deleted' || operation === 'voided') {
                            return 'delete';
                        }

                        return null;
                    }
                },
            ],
        };
    }
})();
