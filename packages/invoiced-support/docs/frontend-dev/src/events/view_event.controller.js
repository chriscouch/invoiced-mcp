/* globals vex */
(function () {
    'use strict';

    angular.module('app.events').controller('ViewEventController', ViewEventController);

    ViewEventController.$inject = ['$scope', '$stateParams', 'Event', 'WebhookAttempt', 'Core'];

    function ViewEventController($scope, $stateParams, Event, WebhookAttempt, Core) {
        $scope.noRetry = {};
        $scope.webhooks = [];
        $scope.relatedTo = [];

        $scope.cmOptions = {
            theme: 'monokai',
            lineNumbers: true,
            lineWrapping: true,
            indentWithTabs: true,
            tabSize: 2,
            matchBrackets: true,
            styleActiveLine: false,
            mode: 'javascript',
            readOnly: true,
        };

        $scope.cmRefresh = 0;

        $scope.showAll = function () {
            $scope.showMore = true;
            $scope.cmRefresh++;
        };

        $scope.retryWebhook = function (id) {
            vex.dialog.confirm({
                message:
                    'Are you sure you want to deliver this webhook again? If your webhook handler has already processed this event it can result in duplicate data.',
                callback: function (result) {
                    if (result) {
                        $scope.noRetry[id] = true;
                        $scope.webhooksError = null;

                        WebhookAttempt.retry(
                            {
                                id: id,
                            },
                            function () {
                                Core.flashMessage('Your webhook has been scheduled for a new attempt.', 'success');
                            },
                            function (result) {
                                $scope.noRetry[id] = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        Core.setTitle('Event Details');
        load($stateParams.id);
        loadWebhooks($stateParams.id);

        function load(id) {
            $scope.loading = true;

            Event.retrieve(
                {
                    id: id,
                },
                function (_event) {
                    $scope.loading = false;
                    $scope.event = _event;
                    $scope.eventData = JSON.stringify(_event, null, 2);
                    $scope.cmRefresh++;

                    // build user information
                    if (_event.user) {
                        if (_event.user.id > 0) {
                            $scope.person = $.trim($scope.event.user.first_name + ' ' + $scope.event.user.last_name);
                        } else if (_event.user.id == -3) {
                            $scope.person = 'API';
                        } else if (_event.user.id == -2) {
                            $scope.person = 'Invoiced';
                        } else if (_event.user.id == -1) {
                            $scope.person = 'Customer';
                        }
                    }

                    // look for key relationships
                    $scope.relatedTo = [];
                    let eventData = _event.data.object;
                    let relatedToName = 'View';
                    if (typeof eventData.number !== 'undefined' && eventData.object !== 'customer') {
                        relatedToName = eventData.number;
                    } else if (typeof eventData.name !== 'undefined') {
                        relatedToName = eventData.name;
                    } else if (typeof eventData.reference !== 'undefined' && eventData.reference) {
                        relatedToName = eventData.reference;
                    }

                    if (eventData.object) {
                        $scope.relatedTo.push({
                            object: eventData.object,
                            id: eventData.id,
                            name: relatedToName,
                        });
                    }

                    if (typeof eventData.customer === 'object') {
                        $scope.relatedTo.push({
                            object: 'customer',
                            id: eventData.customer.id,
                            name: eventData.customer.name,
                        });
                    } else if (eventData.customer > 0) {
                        $scope.relatedTo.push({
                            object: 'customer',
                            id: eventData.customer,
                            name: typeof eventData.customerName !== 'undefined' ? eventData.customerName : 'View',
                        });
                    }

                    if (eventData.payment_id) {
                        $scope.relatedTo.push({
                            object: 'payment',
                            id: eventData.payment_id,
                            name: 'View',
                        });
                    }

                    if (typeof eventData.estimate === 'object') {
                        if (!eventData.estimate && typeof eventData.estimate.customer === 'object') {
                            $scope.relatedTo.push({
                                object: 'customer',
                                id: eventData.estimate.customer.id,
                                name: eventData.estimate.customer.name,
                            });
                        }

                        $scope.relatedTo.push({
                            object: 'estimate',
                            id: eventData.estimate.id,
                            name: eventData.estimate.number,
                        });
                    } else if (eventData.estimate > 0) {
                        $scope.relatedTo.push({
                            object: 'estimate',
                            id: eventData.estimate,
                            name: 'View',
                        });
                    }

                    if (typeof eventData.invoice === 'object') {
                        if (!eventData.customer && typeof eventData.invoice.customer === 'object') {
                            $scope.relatedTo.push({
                                object: 'customer',
                                id: eventData.invoice.customer.id,
                                name: eventData.invoice.customer.name,
                            });
                        }

                        $scope.relatedTo.push({
                            object: 'invoice',
                            id: eventData.invoice.id,
                            name: eventData.invoice.number,
                        });
                    } else if (eventData.invoice > 0) {
                        $scope.relatedTo.push({
                            object: 'invoice',
                            id: eventData.invoice,
                            name: 'View',
                        });
                    }

                    if (typeof eventData.credit_note === 'object') {
                        if (!eventData.customer && typeof eventData.credit_note.customer === 'object') {
                            $scope.relatedTo.push({
                                object: 'customer',
                                id: eventData.credit_note.customer.id,
                                name: eventData.credit_note.customer.name,
                            });
                        }

                        $scope.relatedTo.push({
                            object: 'credit_note',
                            id: eventData.credit_note.id,
                            name: eventData.credit_note.number,
                        });
                    } else if (eventData.credit_note > 0) {
                        $scope.relatedTo.push({
                            object: 'credit_note',
                            id: eventData.credit_note,
                            name: 'View',
                        });
                    }

                    if (eventData.subscription) {
                        $scope.relatedTo.push({
                            object: 'subscription',
                            id: eventData.subscription,
                            name: 'View',
                        });
                    }
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadWebhooks(id) {
            $scope.loadingWebhooks = true;

            WebhookAttempt.findAll(
                {
                    'filter[event_id]': id,
                    paginate: 'none',
                },
                function (webhooks) {
                    $scope.loadingWebhooks = false;
                    $scope.webhooks = webhooks;
                },
                function (result) {
                    $scope.loadingWebhooks = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
