/* jshint -W117, -W030 */
describe('receive payment form service', function () {
    'use strict';

    let ReceivePaymentForm;

    beforeEach(function () {
        module('app.accounts_receivable');

        inject(function (_ReceivePaymentForm_) {
            ReceivePaymentForm = _ReceivePaymentForm_;
        });

        jasmine.addMatchers({
            toEqualData: function () {
                return {
                    compare: function (actual, expected) {
                        let result = {};
                        result.pass = angular.equals(actual, expected);

                        if (result.pass) {
                            result.message =
                                'Expected this:\n' +
                                JSON.stringify(actual, null, 2) +
                                '\nto not match this:\n' +
                                JSON.stringify(expected, null, 2);
                        } else {
                            result.message =
                                'Expected this:\n' +
                                JSON.stringify(actual, null, 2) +
                                '\nto match this:\n' +
                                JSON.stringify(expected, null, 2);
                        }

                        return result;
                    },
                };
            },
        });
    });

    describe('serializeAppliedTo', function () {
        it('should generate an applied to list with only invoices', function () {
            let form = new ReceivePaymentForm('usd', 150);
            form.availableCredits = [];
            form.addDocument(
                {
                    object: 'invoice',
                    id: 1,
                    currency: 'usd',
                },
                false,
                100,
            );
            form.addDocument(
                {
                    object: 'invoice',
                    id: 2,
                    currency: 'usd',
                },
                false,
                50,
            );

            let expected = [
                {
                    type: 'invoice',
                    amount: 100,
                    invoice: 1,
                },
                {
                    type: 'invoice',
                    amount: 50,
                    invoice: 2,
                },
            ];

            let appliedTo = form.serializeAppliedTo();
            expect(appliedTo).toEqualData(expected);
        });
    });

    it('should generate an applied to list with only estimates', function () {
        let form = new ReceivePaymentForm('usd', 150);
        form.availableCredits = [];
        form.addDocument(
            {
                object: 'estimate',
                id: 1,
                currency: 'usd',
            },
            false,
            100,
        );
        form.addDocument(
            {
                object: 'estimate',
                id: 2,
                currency: 'usd',
            },
            false,
            50,
        );

        let expected = [
            {
                type: 'estimate',
                amount: 100,
                estimate: 1,
            },
            {
                type: 'estimate',
                amount: 50,
                estimate: 2,
            },
        ];

        let appliedTo = form.serializeAppliedTo();
        expect(appliedTo).toEqualData(expected);
    });

    it('should generate an applied to list with invoices and estimates', function () {
        let form = new ReceivePaymentForm('usd', 185);
        form.availableCredits = [];
        form.addDocument(
            {
                object: 'invoice',
                id: 1,
                currency: 'usd',
            },
            false,
            100,
        );
        form.addDocument(
            {
                object: 'invoice',
                id: 2,
                currency: 'usd',
            },
            false,
            50,
        );
        form.addDocument(
            {
                object: 'estimate',
                id: 3,
                currency: 'usd',
            },
            false,
            25,
        );
        form.addDocument(
            {
                object: 'estimate',
                id: 4,
                currency: 'usd',
            },
            false,
            10,
        );

        let expected = [
            {
                type: 'invoice',
                amount: 100,
                invoice: 1,
            },
            {
                type: 'invoice',
                amount: 50,
                invoice: 2,
            },
            {
                type: 'estimate',
                amount: 25,
                estimate: 3,
            },
            {
                type: 'estimate',
                amount: 10,
                estimate: 4,
            },
        ];

        let appliedTo = form.serializeAppliedTo();
        expect(appliedTo).toEqualData(expected);
    });

    it('should generate an applied to list with credits applied and zero payment', function () {
        let form = new ReceivePaymentForm('usd', 0);
        form.availableCredits = [
            {
                type: 'applied_credit',
                balance: 125,
                amount: 125,
            },
            {
                type: 'credit_note',
                balance: 60,
                amount: 60,
                document: {
                    object: 'credit_note',
                    id: 5,
                },
            },
        ];
        form.addDocument(
            {
                object: 'invoice',
                id: 1,
                currency: 'usd',
            },
            false,
            100,
        );
        form.addDocument(
            {
                object: 'invoice',
                id: 2,
                currency: 'usd',
            },
            false,
            50,
        );
        form.addDocument(
            {
                object: 'estimate',
                id: 3,
                currency: 'usd',
            },
            false,
            25,
        );
        form.addDocument(
            {
                object: 'estimate',
                id: 4,
                currency: 'usd',
            },
            false,
            10,
        );

        let expected = [
            {
                type: 'applied_credit',
                amount: 100,
                document_type: 'invoice',
                invoice: 1,
            },
            {
                type: 'applied_credit',
                amount: 25,
                document_type: 'invoice',
                invoice: 2,
            },
            {
                type: 'credit_note',
                credit_note: 5,
                document_type: 'invoice',
                amount: 25,
                invoice: 2,
            },
            {
                type: 'credit_note',
                credit_note: 5,
                document_type: 'estimate',
                amount: 25,
                estimate: 3,
            },
            {
                type: 'credit_note',
                credit_note: 5,
                document_type: 'estimate',
                amount: 10,
                estimate: 4,
            },
        ];

        let appliedTo = form.serializeAppliedTo();
        expect(appliedTo).toEqualData(expected);
    });

    it('should generate an applied to list with credits applied and payment', function () {
        let form = new ReceivePaymentForm('usd', 50);
        form.availableCredits = [
            {
                type: 'applied_credit',
                balance: 75,
                amount: 75,
            },
            {
                type: 'credit_note',
                balance: 60,
                amount: 60,
                document: {
                    object: 'credit_note',
                    id: 5,
                },
            },
        ];
        form.addDocument(
            {
                object: 'invoice',
                id: 1,
                currency: 'usd',
            },
            false,
            100,
        );
        form.addDocument(
            {
                object: 'invoice',
                id: 2,
                currency: 'usd',
            },
            false,
            50,
        );
        form.addDocument(
            {
                object: 'estimate',
                id: 3,
                currency: 'usd',
            },
            false,
            25,
        );
        form.addDocument(
            {
                object: 'estimate',
                id: 4,
                currency: 'usd',
            },
            false,
            10,
        );

        let expected = [
            {
                type: 'applied_credit',
                amount: 75,
                document_type: 'invoice',
                invoice: 1,
            },
            {
                type: 'credit_note',
                credit_note: 5,
                document_type: 'invoice',
                amount: 25,
                invoice: 1,
            },
            {
                type: 'credit_note',
                credit_note: 5,
                document_type: 'invoice',
                amount: 35,
                invoice: 2,
            },
            {
                type: 'invoice',
                amount: 15,
                invoice: 2,
            },
            {
                type: 'estimate',
                amount: 25,
                estimate: 3,
            },
            {
                type: 'estimate',
                amount: 10,
                estimate: 4,
            },
        ];

        let appliedTo = form.serializeAppliedTo();
        expect(appliedTo).toEqualData(expected);
    });

    it('should generate an applied to list with a credit applied and overpayment', function () {
        let form = new ReceivePaymentForm('usd', 0);
        form.availableCredits = [
            {
                type: 'applied_credit',
                balance: 50,
                amount: 50,
            },
        ];
        form.appliedTo.push({
            type: 'credit',
            amount: 100,
        });

        let expected = [
            {
                type: 'credit',
                amount: 100,
            },
        ];

        let appliedTo = form.serializeAppliedTo();
        expect(appliedTo).toEqualData(expected);
    });
});
