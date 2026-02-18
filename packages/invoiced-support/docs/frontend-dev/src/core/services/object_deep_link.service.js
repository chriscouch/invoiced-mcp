(function () {
    'use strict';

    angular.module('app.core').factory('ObjectDeepLink', ObjectDeepLink);

    ObjectDeepLink.$inject = ['$state'];

    function ObjectDeepLink($state) {
        return {
            getUrl: getUrl,
            goTo: goTo,
        };

        function getUrl(type, id) {
            let state = getState(type, id);
            if (state) {
                return $state.href(state[0], state[1]);
            }

            return null;
        }

        function goTo(type, id) {
            let state = getState(type, id);
            if (state) {
                $state.go(state[0], state[1]);
            }
        }

        function getState(type, id) {
            if (type === 'bill') {
                return ['manage.bill.view.summary', { id: id }];
            }

            if (type === 'contact') {
                return ['manage.customer.view.summary', { id: id }];
            }

            if (type === 'credit_note') {
                return ['manage.credit_note.view.summary', { id: id }];
            }

            if (type === 'customer') {
                return ['manage.customer.view.summary', { id: id }];
            }

            if (type === 'estimate') {
                return ['manage.estimate.view.summary', { id: id }];
            }

            if (type === 'import') {
                return ['manage.import.view', { id: id }];
            }

            if (type === 'invoice') {
                return ['manage.invoice.view.summary', { id: id }];
            }

            if (type === 'network_document') {
                return ['manage.document.view.summary', { id: id }];
            }

            if (type === 'payment') {
                return ['manage.payment.view.summary', { id: id }];
            }

            if (type === 'payment_link') {
                return ['manage.payment_link.view.summary', { id: id }];
            }

            if (type === 'remittance_advice') {
                return ['manage.remittance_advice.view.summary', { id: id }];
            }

            if (type === 'report') {
                return ['manage.report.view', { id: id }];
            }

            if (type === 'subscription') {
                return ['manage.subscription.view.summary', { id: id }];
            }

            // This supports a hybrid payment or transaction object
            // that determines which page to go to based on object parameters.
            if (type === 'transaction_or_payment') {
                if (id.object === 'payment') {
                    return ['manage.payment.view.summary', { id: id.id }];
                } else if (typeof id.payment !== 'undefined' && id.payment) {
                    return ['manage.payment.view.summary', { id: id.payment }];
                } else {
                    return ['manage.transaction.view.summary', { id: id.id }];
                }
            }

            if (type === 'task') {
                return ['manage.activities.browse', { id: id }];
            }

            if (type === 'transaction') {
                return ['manage.transaction.view.summary', { id: id }];
            }

            if (type === 'vendor') {
                return ['manage.vendor.view.summary', { id: id }];
            }

            if (type === 'vendor_credit') {
                return ['manage.vendor_credit.view.summary', { id: id }];
            }

            if (type === 'vendor_payment') {
                return ['manage.vendor_payment.view.summary', { id: id }];
            }

            if (type === 'vendor_payment_batch') {
                return ['manage.vendor_payment_batches.summary', { id: id }];
            }

            if (type === 'customer_payment_batch') {
                return ['manage.customer_payment_batches.summary', { id: id }];
            }
        }
    }
})();
