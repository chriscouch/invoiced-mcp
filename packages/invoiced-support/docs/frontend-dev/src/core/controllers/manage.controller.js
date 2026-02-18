/* globals moment, snapper */
(function () {
    'use strict';

    angular.module('app.core').controller('ManageController', ManageController);

    ManageController.$inject = [
        '$rootScope',
        '$scope',
        '$state',
        '$modal',
        '$timeout',
        'localStorageService',
        'LeavePageWarning',
        'CurrentUser',
        'Announcement',
        'Core',
        'selectedCompany',
        'Member',
        'UserNotification',
        'Feature',
        'Permission',
        'InvoicedConfig',
    ];

    function ManageController(
        $rootScope,
        $scope,
        $state,
        $modal,
        $timeout,
        lss,
        LeavePageWarning,
        CurrentUser,
        Announcement,
        Core,
        selectedCompany,
        Member,
        UserNotification,
        Feature,
        Permission,
        InvoicedConfig,
    ) {
        //
        // Models
        //

        $scope.company = selectedCompany;
        let user = CurrentUser.profile;
        $scope.user = user;
        $scope.timeoutHandler = null;
        $scope.navigation = [];

        //
        // Presets
        //

        $scope.date = Date.now();
        $scope.searching = false;
        $scope.month = moment().format('MMMM');
        $scope.notificationIndicator = false;
        $scope.invoiceUsageOpts = {
            name: 'Invoice',
            namePlural: 'Invoices',
        };
        $scope.customerUsageOpts = {
            name: 'Customer',
            namePlural: 'Customers',
        };

        $scope.avatarOptions = {
            height: 35,
            width: 35,
        };

        // days left in trial
        $rootScope.trialDaysRemaining = Math.max(
            0,
            moment.unix($scope.company.trial_ends).endOf('day').diff(moment().endOf('day'), 'days'),
        );
        $scope.activateUrl = Core.upgradeUrl(selectedCompany, CurrentUser.profile, true);

        $rootScope.announcements = [];
        $rootScope.notifCount = 0;
        $rootScope.shrinkNav = lss.get('shrinkNav') || false;

        $scope.logoFile = 'top-logo@2x.png';
        if (Feature.hasFeature('enterprise')) {
            $scope.logoFile = 'top-logo-enterprise@2x.png';
        }

        //
        // Methods
        //

        $scope.toggleNavSize = function () {
            $rootScope.shrinkNav = !$rootScope.shrinkNav;
            lss.set('shrinkNav', $rootScope.shrinkNav ? 1 : 0);
        };

        $scope.toggleDrawer = function () {
            if (snapper.state().state == 'left') {
                snapper.close();
                $scope.drawerOpen = false;
            } else {
                snapper.open('left');
                $scope.drawerOpen = true;
            }
        };

        $scope.closeDrawer = function () {
            snapper.close();
            $scope.drawerOpen = false;
        };

        $scope.goNotifications = function () {
            $scope.closeDrawer();
            $state.go($scope.member.notifications ? 'manage.notifications' : 'manage.settings.notifications');
        };

        $scope.switchCompany = function () {
            $scope.closeDrawer();
            $modal.open({
                templateUrl: 'core/views/switch-business.html',
                controller: 'SwitchBusinessController',
                size: 'lg',
            });
        };

        $scope.searchModal = function () {
            $modal.open({
                templateUrl: 'search/views/search-modal.html',
                controller: 'SearchModalController',
                windowClass: 'search-modal',
            });
        };

        $scope.createShortcut = createShortcut;
        $scope.toggleSubmenu = toggleSubmenu;

        //
        // Initialization
        //

        buildNavigation();
        updateLocation();
        $rootScope.$on('$stateChangeSuccess', updateLocation);
        $rootScope.$on('updatePaymentsPage', updateLocation);
        $rootScope.$on('viewNotifications', viewNotifications);

        function viewNotifications() {
            $scope.notificationIndicator = false;
            Member.clearCurrentCache();
        }

        loadAnnouncements();

        Member.current(
            function (member) {
                $scope.member = member;
                if (member && member.notifications) {
                    updateIndicator();
                }
            },
            function (result) {
                $scope.error = result.data;
            },
        );

        function updateIndicator() {
            UserNotification.latest(function (notification) {
                if (!notification) {
                    $scope.timeoutHandler = $timeout(updateIndicator, 10000);
                    return;
                }
                let lastViewed = moment(lss.get('last_notification_viewed') || null);
                let lastNotification = moment(notification.created_at);
                $scope.notificationIndicator = lastViewed < lastNotification;
                $scope.timeoutHandler = $timeout(updateIndicator, 10000);
            });
        }

        function updateLocation() {
            $scope.stateName = $state.current.name;
            $scope.section = $state.current.name.split('.')[1];
            let subsection = '_does_not_exist_';
            if ($scope.section === 'settings') {
                subsection = $scope.section + '.' + $state.current.name.split('.')[2];
            }
            let goToTransactions = !!lss.get('goToTransactionsPage');

            angular.forEach($scope.navigation, function (navItem) {
                navItem.isActive = false;
                if ($state.current.name === navItem.route) {
                    navItem.isActive = true;
                } else if (typeof navItem.activeSections !== 'undefined') {
                    navItem.isActive =
                        navItem.activeSections.indexOf($scope.section) !== -1 ||
                        navItem.activeSections.indexOf(subsection) !== -1;
                }

                if (navItem.isActive && navItem.parent) {
                    angular.forEach($scope.navigation, function (navItem2) {
                        if (navItem2.id === navItem.parent) {
                            openSubmenu(navItem2, true);
                        }
                    });
                }

                if (navItem.id === 'customer-payments') {
                    navItem.route = goToTransactions ? 'manage.transactions.browse' : 'manage.payments.browse';
                }
            });
        }

        function buildNavigation() {
            $scope.navigation = [];
            let submenus = [];
            angular.forEach(InvoicedConfig.appMenu, function (navItem) {
                if (typeof navItem.allFeatures !== 'undefined' && !Feature.hasAllFeatures(navItem.allFeatures)) {
                    return;
                }

                if (typeof navItem.permission !== 'undefined' && !Permission.hasPermission(navItem.permission)) {
                    return;
                }

                if (
                    typeof navItem.createPermission !== 'undefined' &&
                    !Permission.hasPermission(navItem.createPermission)
                ) {
                    navItem.create = false;
                }

                if (
                    typeof navItem.createSomePermissions !== 'undefined' &&
                    !Permission.hasSomePermissions(navItem.createSomePermissions)
                ) {
                    navItem.create = false;
                }

                if (navItem.parent) {
                    navItem.hidden = true;
                }

                if (navItem.type === 'submenu') {
                    navItem.open = false;
                    submenus.push(navItem);
                }

                $scope.navigation.push(navItem);
            });

            // If there is only a single submenu, then open it by default
            if (submenus.length === 1) {
                openSubmenu(submenus[0], true);
            }
        }

        function toggleSubmenu(navItem) {
            openSubmenu(navItem, !navItem.open);
        }

        function openSubmenu(navItem, open) {
            navItem.open = open;
            angular.forEach($scope.navigation, function (navItem2) {
                if (navItem2.type === 'submenu' && navItem2.id !== navItem.id) {
                    navItem2.open = false;
                }

                if (navItem2.parent) {
                    navItem2.hidden = navItem2.parent !== navItem.id || !navItem.open;
                }
            });
        }

        function createShortcut(navItem) {
            if (typeof navItem.createRoute !== 'undefined') {
                $state.go(navItem.createRoute);
            } else if (navItem.id === 'customers') {
                customerModal();
            } else if (navItem.id === 'subscriptions') {
                subscriptionModal();
            } else if (navItem.id === 'customer_payments') {
                paymentModal();
            } else if (navItem.id === 'vendors') {
                vendorModal();
            }
        }

        function customerModal() {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/customers/edit-customer.html',
                controller: 'EditCustomerController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    model: function () {
                        return false;
                    },
                },
            });

            modalInstance.result.then(
                function (customer) {
                    LeavePageWarning.unblock();

                    Core.flashMessage('A customer profile for ' + customer.name + ' was created', 'success');

                    // TODO should we notify other controllers that a customer has been added?

                    // if nothing else is blocking, open the new customer
                    if (LeavePageWarning.canLeave()) {
                        $state.go('manage.customer.view.summary', {
                            id: customer.id,
                        });
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        }

        function vendorModal() {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_payable/views/vendors/edit.html',
                controller: 'EditVendorController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    model: function () {
                        return null;
                    },
                },
            });

            modalInstance.result.then(
                function (vendor) {
                    LeavePageWarning.unblock();

                    Core.flashMessage('A vendor profile for ' + vendor.name + ' was created', 'success');

                    // if nothing else is blocking, open the new customer
                    if (LeavePageWarning.canLeave()) {
                        $state.go('manage.vendor.view.summary', {
                            id: vendor.id,
                        });
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        }

        function subscriptionModal() {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'subscriptions/views/new-subscription.html',
                controller: 'NewSubscriptionController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    customer: function () {
                        return false;
                    },
                },
            });

            modalInstance.result.then(
                function (subscription) {
                    LeavePageWarning.unblock();

                    Core.flashMessage('Your customer was subscribed to ' + subscription.plan, 'success');

                    // TODO should we notify other controllers that a subscription has been added?

                    // if nothing else is blocking, open the new subscription
                    if (LeavePageWarning.canLeave()) {
                        $state.go('manage.subscription.view.summary', {
                            id: subscription.id,
                        });
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        }

        function paymentModal() {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/payments/receive-payment.html',
                controller: 'ReceivePaymentController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    options: function () {
                        return {};
                    },
                },
            });

            modalInstance.result.then(
                function (payment) {
                    LeavePageWarning.unblock();

                    Core.flashMessage('Your payment has been recorded', 'success');

                    // if nothing else is blocking, open the new payment
                    if (LeavePageWarning.canLeave()) {
                        if (payment.object === 'transaction') {
                            $state.go('manage.transaction.view.summary', {
                                id: payment.id,
                            });
                        } else {
                            $state.go('manage.payment.view.summary', {
                                id: payment.id,
                            });
                        }
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        }

        function loadAnnouncements() {
            Announcement.get(function (announcements) {
                $rootScope.announcements = announcements;

                // determine date last checked
                // max or date signed up, last week, or last time checked
                let lastChecked = moment
                    .unix(
                        Math.max(
                            user.created_at,
                            parseInt(lss.get('announcementsLastRead') || 0),
                            moment().subtract(7, 'days').unix(),
                        ),
                    )
                    .toDate();

                // determine number of new announcements
                $rootScope.notifCount = 0;
                angular.forEach(announcements, function (announcement) {
                    if (announcement.date > lastChecked) {
                        $rootScope.notifCount++;
                    }
                });
            });
        }
    }
})();
