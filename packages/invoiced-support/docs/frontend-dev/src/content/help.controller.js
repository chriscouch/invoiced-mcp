/* globals zE */
(function () {
    'use strict';

    angular.module('app.content').controller('HelpController', HelpController);

    HelpController.$inject = [
        '$scope',
        '$window',
        '$timeout',
        '$interval',
        'CurrentUser',
        'selectedCompany',
        'InvoicedConfig',
        'Core',
        'Feature',
        'SupportTicket',
        'localStorageService',
    ];

    function HelpController(
        $scope,
        $window,
        $timeout,
        $interval,
        CurrentUser,
        selectedCompany,
        InvoicedConfig,
        Core,
        Feature,
        SupportTicket,
        localStorageService,
    ) {
        // navigation
        $scope.section = '';
        $scope.hasFeedback = false;
        $scope.hasEmailSupport = false;
        $scope.hasLiveChatSupport = false;
        $scope.hasPhoneSupport = false;
        $scope.supportPin = null;
        if (selectedCompany.features) {
            $scope.hasFeedback = true;
            $scope.hasEmailSupport = true;
            $scope.hasLiveChatSupport = Feature.hasFeature('live_chat');
            $scope.hasPhoneSupport = Feature.hasFeature('phone_support');
        } else {
            // This means the user is signed out
            $scope.hasEmailSupport = true;
        }

        $scope.supportCategories = [
            // General
            { name: 'Account (User / Company Profile)', tag: 'account', section: 'General' },
            { name: 'My Invoiced Bill', tag: 'my_invoiced_bill', section: 'General' },
            { name: 'Other', tag: 'other', section: 'General' },
            // Product
            { name: 'API / Webhooks', tag: 'api', section: 'Product' },
            { name: 'Cash Application', tag: 'cash_application', section: 'Product' },
            { name: 'Chasing', tag: 'chasing', section: 'Product' },
            { name: 'Customer Portal', tag: 'customer_portal', section: 'Product' },
            { name: 'Email / Inbox', tag: 'email', section: 'Product' },
            { name: 'Estimates', tag: 'estimates', section: 'Product' },
            { name: 'Payment Plans', tag: 'payment_plans', section: 'Product' },
            { name: 'Payment Processing', tag: 'payment_processing', section: 'Product' },
            { name: 'Reporting', tag: 'reporting', section: 'Product' },
            { name: 'Subscription Billing', tag: 'subscription_billing', section: 'Product' },
            // Integrations
            { name: 'Avalara Integration', tag: 'avalara_integration', section: 'Integrations' },
            { name: 'ChartMogul Integration', tag: 'chartmogul_integration', section: 'Integrations' },
            { name: 'Earth Class Mail Integration', tag: 'earth_class_mail_integration', section: 'Integrations' },
            { name: 'ERP Connect', tag: 'erp_connect', section: 'Integrations' },
            { name: 'Lob Integration', tag: 'lob_integration', section: 'Integrations' },
            { name: 'NetSuite Integration', tag: 'netsuite_integration', section: 'Integrations' },
            {
                name: 'QuickBooks Desktop Integration',
                tag: 'quickbooks_desktop_integration',
                section: 'Integrations',
            },
            { name: 'QuickBooks Online Integration', tag: 'quickbooks_online_integration', section: 'Integrations' },
            { name: 'Sage Intacct Integration', tag: 'sage_intacct_integration', section: 'Integrations' },
            { name: 'Salesforce Integration', tag: 'salesforce_integration', section: 'Integrations' },
            { name: 'Stitch Integration', tag: 'stitch_integration', section: 'Integrations' },
            { name: 'Twilio Integration', tag: 'twilio_integration', section: 'Integrations' },
            { name: 'Xero Integration', tag: 'xero_integration', section: 'Integrations' },
            { name: 'Zapier Integration', tag: 'zapier_integration', section: 'Integrations' },
        ];

        // create a ticket
        $scope.ticketSubmitted = false;
        $scope.email = CurrentUser.profile.email;
        $scope.name = CurrentUser.profile.name;
        $scope.message = '';
        $scope.createTicket = createTicket;

        $scope.liveChatAvailable = $window.chatOperatorsAvailable;

        $scope.liveChat = function () {
            zE('webWidget', 'show');
        };

        Core.setTitle('Help & Support');

        if ($scope.hasPhoneSupport) {
            CurrentUser.supportPin(function (result) {
                $scope.supportPin = result.support_pin;
            });
        }

        // Periodically check if live chat is available
        $interval(function () {
            $scope.liveChatAvailable = $window.chatOperatorsAvailable;
        }, 1000);

        function createTicket(name, email, category, message) {
            let subject = category.name + ' (' + selectedCompany.name + ')';
            let tags = ['in_app'];

            $scope.sending = true;
            SupportTicket.create(
                {
                    company: selectedCompany.id,
                    name: name,
                    email: email,
                    subject: subject,
                    category: category.name,
                    category_tag: category.tag,
                    message: message,
                    tags: tags,
                    current_page: localStorageService.get('helpCurrentPage'),
                },
                function (result) {
                    $scope.sending = false;
                    $scope.ticketSubmitted = true;
                    $scope.ticketNumber = result.ticket_number;

                    // upload file attachments
                    $('#supportTicketNumber').val(result.ticket_number);
                    $('#supportMessageId').val(result.ticket_number);
                    let uploadUrl = InvoicedConfig.baseUrl + '/support_ticket_attachments';
                    $('#ticketFileAttachments').attr('action', uploadUrl).submit();
                },
                function (result) {
                    $scope.sending = false;
                    Core.flashMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
