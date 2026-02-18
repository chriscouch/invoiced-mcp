(function () {
    'use strict';

    /*
     * Permissions Format
     *
     * id:           id corresponding to Roles column. General format is '<object>_<action>'.
     *
     * name:         Short description of permission. General format is '<Action> <Object>'.
     *
     * group:        Group label for filtering in UI. General format is '<Object>'.
     *               Use Pascal case for multi-word objects (ex. CreditNotes).
     *
     * description:  Simple description used for the tooltip in the UI.
     *               General format is 'Allows users to <action> <object>'.
     *
     * */

    this.InvoicedConfig.permissions = [
        {
            id: 'customers_create',
            name: 'Create Customers',
            group: 'Customers',
            description: 'Allows users to create customers.',
        },
        {
            id: 'customers_edit',
            name: 'Edit Customers',
            group: 'Customers',
            description: 'Allows users to modify customers.',
        },
        {
            id: 'customers_delete',
            name: 'Delete Customers',
            group: 'Customers',
            description: 'Allows users to delete customers.',
        },
        {
            id: 'invoices_create',
            name: 'Create Invoices',
            group: 'Invoices',
            description: 'Allows users to create invoices.',
        },
        {
            id: 'invoices_edit',
            name: 'Edit Invoices',
            group: 'Invoices',
            description: 'Allows users to modify invoices.',
        },
        {
            id: 'invoices_issue',
            name: 'Issue Invoices',
            group: 'Invoices',
            description: 'Allows users to issue invoices.',
        },
        {
            id: 'invoices_void',
            name: 'Void Invoices',
            group: 'Invoices',
            description: 'Allows users to void invoices.',
        },
        {
            id: 'invoices_delete',
            name: 'Delete Invoices',
            group: 'Invoices',
            description: 'Allows users to delete invoices.',
        },
        {
            id: 'credit_notes_create',
            name: 'Create Credit Notes',
            group: 'CreditNotes',
            description: 'Allows users to create credit notes.',
        },
        {
            id: 'credit_notes_edit',
            name: 'Edit Credit Notes',
            group: 'CreditNotes',
            description: 'Allows users to modify credit notes.',
        },
        {
            id: 'credit_notes_issue',
            name: 'Issue Credit Notes',
            group: 'CreditNotes',
            description: 'Allows users to issue credit notes.',
        },
        {
            id: 'credit_notes_void',
            name: 'Void Credit Notes',
            group: 'CreditNotes',
            description: 'Allows users to void credit notes.',
        },
        {
            id: 'credit_notes_delete',
            name: 'Delete Credit Notes',
            group: 'CreditNotes',
            description: 'Allows users to delete credit notes.',
        },
        {
            id: 'estimates_create',
            name: 'Create Estimates',
            group: 'Estimates',
            description: 'Allows users to create estimates.',
        },
        {
            id: 'estimates_edit',
            name: 'Edit Estimates',
            group: 'Estimates',
            description: 'Allows users to modify estimates.',
        },
        {
            id: 'estimates_issue',
            name: 'Issue Estimates',
            group: 'Estimates',
            description: 'Allows users to issue estimates.',
        },
        {
            id: 'estimates_void',
            name: 'Void Estimates',
            group: 'Estimates',
            description: 'Allows users to void estimates.',
        },
        {
            id: 'estimates_delete',
            name: 'Delete Estimates',
            group: 'Estimates',
            description: 'Allows users to delete estimates.',
        },
        {
            id: 'emails_send',
            name: 'Send Emails',
            group: 'Sending',
            description: 'Allows users to send emails.',
        },
        {
            id: 'text_messages_send',
            name: 'Send Texts',
            group: 'Sending',
            description: 'Allows users to send text messages.',
        },
        {
            id: 'letters_send',
            name: 'Send Letters',
            group: 'Sending',
            description: 'Allows users to send letters.',
        },
        {
            id: 'payments_create',
            name: 'Create Payments',
            group: 'Payments',
            description: 'Allows users to add payments.',
        },
        {
            id: 'payments_edit',
            name: 'Edit Payments',
            group: 'Payments',
            description: 'Allows users to modify payments.',
        },
        {
            id: 'payments_delete',
            name: 'Delete Payments',
            group: 'Payments',
            description: 'Allows users to delete payments.',
        },
        {
            id: 'charges_create',
            name: 'Create Charges',
            group: 'Payments',
            description: 'Allows users to create charges (i.e. credit card and ACH).',
        },
        {
            id: 'refunds_create',
            name: 'Create Refunds',
            group: 'Payments',
            description: 'Allows users to refund charges and payments.',
        },
        {
            id: 'credits_create',
            name: 'Create Credits',
            group: 'Credits',
            description: 'Allows users to add credits to an account.',
        },
        {
            id: 'credits_apply',
            name: 'Apply Credits',
            group: 'Credits',
            description: 'Allows users to apply credits to invoices.',
        },
        {
            id: 'reports_create',
            name: 'Create Reports',
            group: 'Reporting',
            description: 'Allows users to use the dashboard, reporting, and exports.',
        },
        {
            id: 'imports_create',
            name: 'Create Imports',
            group: 'Importing',
            description: 'Allows users to create imports of any type that the user has the ability to create.',
        },
        {
            id: 'settings_edit',
            name: 'Edit Settings',
            group: 'Settings',
            description:
                'Allows users to modify the business profile, payment settings, and other settings like ' +
                'chasing and email templates.',
        },
        {
            id: 'catalog_edit',
            name: 'Edit Catalog',
            group: 'Settings',
            description: 'Allows users to manage the pricing catalog, including items, plans, coupons, and taxes.',
        },
        {
            id: 'business_admin',
            name: 'Business Admin',
            group: 'Settings',
            description:
                'Allows users to control access to the business and the ability to add and remove team members.',
        },
        {
            id: 'business_billing',
            name: 'Business Billing',
            group: 'Settings',
            description:
                'Gives a user access to the billing from Invoiced. They can manage payment information, view\n' +
                'invoices, upgrade, downgrade, and cancel.',
        },
        {
            id: 'notifications_edit',
            name: 'Manage Notifications',
            group: 'Accounts',
            description: 'Gives a user ability to set individual notification preferences.',
        },
        {
            id: 'comments_create',
            name: 'Create Comments',
            group: 'Comments',
            description: 'Allows users to create comments.',
        },
        {
            id: 'notes_create',
            name: 'Create Notes',
            group: 'SystemNotes',
            description: 'Allows users to create notes.',
        },
        {
            id: 'notes_edit',
            name: 'Edit Notes',
            group: 'SystemNotes',
            description: 'Allows users to modify notes.',
        },
        {
            id: 'notes_delete',
            name: 'Delete Notes',
            group: 'SystemNotes',
            description: 'Allows users to delete notes.',
        },
        {
            id: 'subscriptions_create',
            name: 'Create Subscriptions',
            group: 'Subscriptions',
            description: 'Allows users to create subscriptions.',
        },
        {
            id: 'subscriptions_edit',
            name: 'Edit Subscriptions',
            group: 'Subscriptions',
            description: 'Allows users to modify subscriptions.',
        },
        {
            id: 'subscriptions_delete',
            name: 'Delete Subscriptions',
            group: 'Subscriptions',
            description: 'Allows users to delete subscriptions.',
        },
        {
            id: 'tasks_create',
            name: 'Create Tasks',
            group: 'Tasks',
            description: 'Allows users to create tasks.',
        },
        {
            id: 'tasks_edit',
            name: 'Edit Tasks',
            group: 'Tasks',
            description: 'Allows users to modify tasks.',
        },
        {
            id: 'tasks_delete',
            name: 'Delete Tasks',
            group: 'Tasks',
            description: 'Allows users to delete tasks.',
        },
        {
            id: 'vendors_create',
            name: 'Create Vendors',
            group: 'Vendors',
            description: 'Allows users to create vendors.',
        },
        {
            id: 'vendors_edit',
            name: 'Edit Vendors',
            group: 'Vendors',
            description: 'Allows users to modify vendors.',
        },
        {
            id: 'vendors_delete',
            name: 'Delete Vendors',
            group: 'Vendors',
            description: 'Allows users to delete vendors.',
        },
        {
            id: 'bills_create',
            name: 'Create Bills',
            group: 'Bills',
            description: 'Allows users to create bills.',
        },
        {
            id: 'bills_edit',
            name: 'Edit Bills',
            group: 'Bills',
            description: 'Allows users to modify bills.',
        },
        {
            id: 'bills_delete',
            name: 'Delete Bills',
            group: 'Bills',
            description: 'Allows users to delete bills.',
        },
        {
            id: 'vendor_payments_create',
            name: 'Create Vendor Payments',
            group: 'VendorPmnts',
            description: 'Allows users to create vendor payments.',
        },
        {
            id: 'vendor_payments_edit',
            name: 'Edit Vendor Payments',
            group: 'VendorPmnts',
            description: 'Allows users to modify vendor payments.',
        },
        {
            id: 'vendor_payments_delete',
            name: 'Delete Vendor Payments',
            group: 'VendorPmnts',
            description: 'Allows users to delete vendor payments.',
        },
    ];
}).call(this);
