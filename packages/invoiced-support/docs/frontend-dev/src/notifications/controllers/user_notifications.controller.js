/* globals moment */
(function () {
    'use strict';

    angular.module('app.core').controller('UserNotificationsController', UserNotificationsController);

    UserNotificationsController.$inject = [
        '$state',
        '$rootScope',
        '$q',
        '$scope',
        'Core',
        'localStorageService',
        'UserNotification',
    ];

    function UserNotificationsController($state, $rootScope, $q, $scope, Core, lss, UserNotification) {
        //
        // Models
        //

        $scope.user_notifications = [];
        $scope.user_notifications_total = 0;
        $scope.paginationTable = {
            page: 1,
            per_page: 100,
            hasMore: true,
            loading: true,
        };
        $scope.loading = 0;
        //
        // Initialization
        //

        Core.setTitle('Notifications');

        $scope.nextPage = function () {
            ++$scope.paginationTable.page;
            loadUserNotifications();
        };

        $scope.goToNotification = function (notification) {
            switch (notification.type) {
                case 'email.received':
                    if (notification.metadata.related_to_type === 'invoice') {
                        $state.go('manage.invoice.view.messages', {
                            id: notification.metadata.related_to_id,
                        });
                    } else if (notification.metadata.related_to_type === 'credit_note') {
                        $state.go('manage.credit_note.view.messages', {
                            id: notification.metadata.related_to_id,
                        });
                    } else if (notification.metadata.related_to_type === 'estimate') {
                        $state.go('manage.estimate.view.messages', {
                            id: notification.metadata.related_to_id,
                        });
                    } else if (notification.metadata.related_to_type === 'bill') {
                        $state.go('manage.bill.view.messages', {
                            id: notification.metadata.related_to_id,
                        });
                    } else if (notification.metadata.related_to_type === 'vendor_credit') {
                        $state.go('manage.vendor_credit.view.messages', {
                            id: notification.metadata.related_to_id,
                        });
                    } else {
                        $state.go('manage.inboxes.browse.view_thread', {
                            id: notification.metadata.inbox_id,
                            threadId: notification.metadata.thread_id,
                            emailId: notification.metadata.id,
                        });
                    }

                    return;
                case 'thread.assigned':
                    $state.go('manage.inboxes.browse.view_thread', {
                        id: notification.metadata.inbox_id,
                        threadId: notification.metadata.id,
                    });
                    return;
                case 'task.assigned':
                    if (notification.metadata.action === 'approve_bill') {
                        $state.go('manage.bill.view.summary', {
                            id: notification.metadata.bill_id,
                        });
                        return;
                    }
                    $state.go('manage.customer.view.collections', {
                        id: notification.metadata.customer_id,
                    });
                    return;
                case 'invoice.viewed':
                    $state.go('manage.invoice.view.summary', {
                        id: notification.object_id,
                    });
                    return;
                case 'estimate.viewed':
                    $state.go('manage.estimate.view.summary', {
                        id: notification.object_id,
                    });
                    return;
                case 'estimate.approved':
                    $state.go('manage.estimate.view.summary', {
                        id: notification.object_id,
                    });
                    return;
                case 'lock_box.received':
                case 'payment.done':
                    $state.go('manage.payment.view.summary', {
                        id: notification.object_id,
                    });
                    return;
                case 'promise.created':
                    $state.go('manage.invoice.view.summary', {
                        id: notification.metadata.invoice_id,
                    });
                    return;
                case 'payment_plan.approved':
                    $state.go('manage.invoice.view.summary', {
                        id: notification.metadata.invoice_id,
                    });
                    return;
                case 'sign_up_page.completed':
                    if (notification.metadata.customer_id) {
                        $state.go('manage.customer.view.summary', {
                            id: notification.metadata.customer_id,
                        });
                    }
                    return;
                case 'autopay.failed':
                case 'autopay.succeeded':
                    if (notification.metadata.payment_id) {
                        $state.go('manage.payment.view.summary', {
                            id: notification.metadata.payment_id,
                        });
                    }
                    return;
                case 'subscription.canceled':
                case 'subscription.expired':
                    $state.go('manage.subscription.view.summary', {
                        id: notification.object_id,
                    });
                    return;
                case 'network_document.received':
                    $state.go('manage.document.view.summary', {
                        id: notification.object_id,
                    });
                    return;
                case 'network_document_status.changed':
                    $state.go('manage.document.view.summary', {
                        id: notification.metadata.document_id,
                    });
                    return;
                case 'payment_link.completed':
                    if (notification.metadata.customer_id) {
                        $state.go('manage.customer.view.summary', {
                            id: notification.metadata.customer_id,
                        });
                    }
                    return;
            }
        };

        $scope.loading++;
        loadUserNotifications();

        function loadUserNotifications() {
            $scope.paginationTable.loading = true;
            UserNotification.findAll(
                {
                    per_page: $scope.paginationTable.per_page,
                    page: $scope.paginationTable.page,
                    paginate: 'none',
                },
                function (data, headers) {
                    $scope.paginationTable.hasMore = headers('X-Has-More') === '1';
                    if (data) {
                        let result = data.map(function (notification) {
                            let lsLastViewed = lss.get('last_notification_viewed');
                            if (lsLastViewed) {
                                let lastViewed = moment(lsLastViewed);
                                let lastNotification = moment(notification.created_at);
                                if (lastViewed > lastNotification) {
                                    notification.visited = true;
                                    lss.set('last_notification_viewed', moment().toISOString());
                                }
                            }
                            return notification;
                        });
                        $scope.user_notifications = $scope.user_notifications.concat(result);
                        $rootScope.$broadcast('viewNotifications');
                    }
                    $scope.loading = 0;
                    $scope.paginationTable.loading = false;
                },
                function (result) {
                    $scope.error = result.data;
                    $scope.loading = 0;
                    $scope.paginationTable.loading = false;
                },
            );
        }
    }
})();
