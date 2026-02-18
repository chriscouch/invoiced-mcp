(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('Customer', Customer);

    Customer.$inject = ['$resource', 'InvoicedConfig'];

    function Customer($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/customers/:id/:item/:subid/:subsubid',
            {
                id: '@id',
                subid: '@subid',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                    params: {
                        exclude: 'metadata',
                    },
                },
                find: {
                    method: 'GET',
                    params: {
                        include: 'address',
                    },
                },
                create: {
                    method: 'POST',
                },
                edit: {
                    method: 'PATCH',
                    params: {
                        include: 'address',
                    },
                },
                delete: {
                    method: 'DELETE',
                },
                balance: {
                    method: 'GET',
                    params: {
                        item: 'balance',
                    },
                },
                merge: {
                    method: 'POST',
                    params: {
                        item: 'merge',
                    },
                },
                sendInvoiced: {
                    method: 'POST',
                    params: {
                        item: 'send',
                    },
                },
                email: {
                    method: 'POST',
                    params: {
                        item: 'emails',
                    },
                    isArray: true,
                },
                sendLetter: {
                    method: 'POST',
                    params: {
                        item: 'letters',
                    },
                },
                sendTextMessage: {
                    method: 'POST',
                    params: {
                        item: 'text_messages',
                    },
                    isArray: true,
                },
                getDisabledPaymentMethods: {
                    metohd: 'GET',
                    params: {
                        item: 'disabled_payment_methods',
                    },
                    isArray: true,
                },
                consolidateInvoices: {
                    method: 'POST',
                    params: {
                        item: 'consolidate_invoices',
                    },
                },
                upcomingInvoice: {
                    method: 'GET',
                    params: {
                        item: 'upcoming_invoice',
                    },
                },
                collectionActivity: {
                    metohd: 'GET',
                    params: {
                        item: 'collection_activity',
                    },
                },
                accountingSyncStatus: {
                    method: 'GET',
                    params: {
                        item: 'accounting_sync_status',
                    },
                },

                /* Contacts */

                contacts: {
                    method: 'GET',
                    params: {
                        item: 'contacts',
                        include: 'address',
                    },
                    isArray: true,
                },
                findContact: {
                    method: 'GET',
                    params: {
                        item: 'contacts',
                        include: 'address',
                    },
                },
                createContact: {
                    method: 'POST',
                    params: {
                        item: 'contacts',
                        include: 'address',
                    },
                },
                editContact: {
                    method: 'PATCH',
                    params: {
                        item: 'contacts',
                        include: 'address',
                    },
                },
                deleteContact: {
                    method: 'DELETE',
                    params: {
                        item: 'contacts',
                    },
                },

                /* Contact Roles */
                contactRoles: {
                    url: InvoicedConfig.apiBaseUrl + '/contact_roles',
                    method: 'GET',
                    isArray: true,
                },
                findContactRole: {
                    url: InvoicedConfig.apiBaseUrl + '/contact_roles/:id',
                    method: 'GET',
                },
                createContactRole: {
                    url: InvoicedConfig.apiBaseUrl + '/contact_roles',
                    method: 'POST',
                },
                editContactRole: {
                    url: InvoicedConfig.apiBaseUrl + '/contact_roles/:id',
                    method: 'PATCH',
                },
                deleteContactRole: {
                    url: InvoicedConfig.apiBaseUrl + '/contact_roles/:id',
                    method: 'DELETE',
                },

                /* Payment Methods */

                paymentSources: {
                    method: 'GET',
                    params: {
                        item: 'payment_sources',
                    },
                    isArray: true,
                },
                addPaymentSource: {
                    method: 'POST',
                    params: {
                        item: 'payment_sources',
                    },
                },
                verifyBankAccount: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/customers/:customer/bank_accounts/:id/verify',
                },
                reinstateMandate: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/customers/:id/bank_accounts/:subid/reinstate',
                },
                deleteBankAccount: {
                    method: 'DELETE',
                    params: {
                        item: 'bank_accounts',
                    },
                },
                deleteCard: {
                    method: 'DELETE',
                    params: {
                        item: 'cards',
                    },
                },

                /* Pending Line Items */

                lineItems: {
                    url: InvoicedConfig.apiBaseUrl + '/customers/:customer/line_items',
                    method: 'GET',
                    params: {
                        customer: '@customer',
                    },
                    isArray: true,
                },
                createLineItem: {
                    url: InvoicedConfig.apiBaseUrl + '/customers/:customer/line_items',
                    method: 'POST',
                    params: {
                        customer: '@customer',
                    },
                },
                editLineItem: {
                    url: InvoicedConfig.apiBaseUrl + '/customers/:customer/line_items/:id',
                    method: 'PATCH',
                    params: {
                        customer: '@customer',
                        id: '@id',
                    },
                },
                deleteLineItem: {
                    url: InvoicedConfig.apiBaseUrl + '/customers/:customer/line_items/:id',
                    method: 'DELETE',
                    params: {
                        customer: '@customer',
                        id: '@id',
                    },
                },
                invoice: {
                    method: 'POST',
                    params: {
                        item: 'invoices',
                    },
                },
                notes: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/customers/:id/notes',
                    isArray: true,
                },
                tasks: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/customers/:id/tasks',
                    isArray: true,
                },
                /* Attachments */

                attachments: {
                    method: 'GET',
                    params: {
                        item: 'attachments',
                    },
                    isArray: true,
                },
                deleteAttachment: {
                    method: 'DELETE',
                    params: {
                        item: 'attachments',
                    },
                    isArray: true,
                },
            },
        );
    }
})();
