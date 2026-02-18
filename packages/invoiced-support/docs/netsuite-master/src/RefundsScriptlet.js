/**
 * @NApiVersion 2.x
 * @NScriptType Restlet
 * @NModuleScope Public
 */
define(['N/record', 'N/search', 'N/log'], function (record, search, log) {
    /**
     * Invoiced ID for record object
     * @type {string}
     */
    const INVOICED_ID_TRANSACTION_BODY_FIELD = 'custbody_invoiced_id';

    function findOne(type, filters) {
        let s = search.create({
            type: type,
            filters: filters,
        });
        s = s.run();
        return s.getRange({
            start: 0,
            end: 1,
        })[0];
    }

    function populateRefund(refund, params) {
        refund.setValue({
            fieldId: INVOICED_ID_TRANSACTION_BODY_FIELD,
            value: params[INVOICED_ID_TRANSACTION_BODY_FIELD],
            ignoreFieldChange: true,
        });
    }

    function doPost(params) {
        log.debug('Called from POST', params);
        let id = params.payment;

        let refund = record.transform({
            fromType: record.Type.CUSTOMER_PAYMENT,
            fromId: id,
            toType: record.Type.CUSTOMER_REFUND,
            isDynamic: true,
        });
        let paymentmethod = refund.getValue({
            fieldId: 'paymentmethod',
        });
        //set random payment method if none is found
        if (!paymentmethod) {
            let firstResult = findOne(record.Type.PAYMENT_METHOD);
            log.debug('Payment method', firstResult);
            paymentmethod = firstResult.id;
            refund.setValue({
                fieldId: 'paymentmethod',
                value: paymentmethod,
                ignoreFieldChange: true,
            });
        }
        populateRefund(refund, params);

        let length = refund.getLineCount({
            sublistId: 'apply',
        });
        log.debug('Line Count', length);
        let listId;

        for (let i = 0; i < length; ++i) {
            refund.selectLine({
                sublistId: 'apply',
                line: i,
            });
            listId = refund.getCurrentSublistValue({
                sublistId: 'apply',
                fieldId: 'doc',
            });
            log.debug('listId', listId);
            if (listId !== id) {
                continue;
            }
            //we have fund what we seek
            refund.setCurrentSublistValue({
                sublistId: 'apply',
                fieldId: 'amount',
                value: params.amount,
                ignoreFieldChange: true,
            });
            refund.setCurrentSublistValue({
                sublistId: 'apply',
                fieldId: 'apply',
                value: true,
                ignoreFieldChange: true,
            });
            refund.commitLine({
                sublistId: 'apply',
            });
            log.debug('Refund created', refund);
            refund.save();
            return refund;
        }
        //if no payment was found we will try to refund credit memo
        let payment = record.load({
            type: record.Type.CUSTOMER_PAYMENT,
            isDynamic: true,
            id: id,
        });
        if (!payment) {
            log.debug('No payment was found');
        }
        let creditMemos = {};
        log.debug('Payment found', payment);
        length = payment.getLineCount({
            sublistId: 'apply',
        });
        //get list of invoices payment applied to
        for (let i = 0; i < length; ++i) {
            payment.selectLine({
                sublistId: 'apply',
                line: i,
            });
            let apply = payment.getCurrentSublistValue({
                sublistId: 'apply',
                fieldId: 'apply',
            });
            //we don't care about unaplied items
            if (!apply) {
                continue;
            }
            let invoiceId = payment.getCurrentSublistValue({
                sublistId: 'apply',
                fieldId: 'doc',
            });
            let creditNote = findOne(record.Type.CREDIT_MEMO, ['createdFrom', search.Operator.IS, invoiceId]);
            //we don't care if no credit note exist
            if (!creditNote) {
                continue;
            }
            creditMemos[creditNote.id] = creditNote.getValue({
                fieldId: 'total',
            });
        }

        refund = record.create({
            type: record.Type.CUSTOMER_REFUND,
            isDynamic: true,
        });

        refund.setValue({
            fieldId: 'customer',
            value: params.customer,
        });
        refund.setValue({
            fieldId: 'paymentmethod',
            value: paymentmethod,
            ignoreFieldChange: true,
        });
        populateRefund(refund, params);

        let applied = 0;
        length = refund.getLineCount({
            sublistId: 'apply',
        });
        log.debug('Line Count', length);
        log.debug('Credit memos', creditMemos);
        for (let i = 0; i < length; ++i) {
            refund.selectLine({
                sublistId: 'apply',
                line: i,
            });
            listId = refund.getCurrentSublistValue({
                sublistId: 'apply',
                fieldId: 'doc',
            });
            log.debug('List id', listId);
            if (creditMemos[listId] === undefined) {
                continue;
            }
            let amount = creditMemos[listId];
            applied += amount;
            refund.setCurrentSublistValue({
                sublistId: 'apply',
                fieldId: 'amount',
                value: amount,
                ignoreFieldChange: true,
            });
            refund.setCurrentSublistValue({
                sublistId: 'apply',
                fieldId: 'apply',
                value: true,
                ignoreFieldChange: true,
            });
            refund.commitLine({
                sublistId: 'apply',
            });
        }
        //if applicable amount not equal payment amount - we ignore insert
        if (applied !== params.amount) {
            log.error(
                'Amount mismatch, We can not determine the refunded entities based on provided refund amount.',
                applied + ' != ' + params.amount,
            );
            return;
        }
        refund.save();
        return refund;
    }

    return {
        //get:
        post: doPost,
        //put:
        //delete:
    };
});
