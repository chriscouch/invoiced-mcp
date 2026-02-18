(function () {
    'use strict';

    angular.module('app', [
        // shared modules
        'app.core',
        'app.components',

        // features
        'app.developer_tools',
        'app.auth',
        'app.billing',
        'app.content',
        'app.dashboard',
        'app.sending',
        'app.events',
        'app.files',
        'app.imports',
        'app.accounts_receivable',
        'app.user_management',
        'app.payment_setup',
        'app.payment_plans',
        'app.reports',
        'app.search',
        'app.settings',
        'app.subscriptions',
        'app.themes',
        'app.inboxes',
        'app.notifications',
        'app.network',
        'app.accounts_payable',
    ]);
})();
