/**
 * @NApiVersion 2.x
 * @NScriptType Restlet
 * @NModuleScope Public
 */
define(['N/record', 'N/log'], function (record, log) {
    /**
     * Invoiced ID for record object
     * @type {string}
     */
    const INVOICED_ID_TRANSACTION_BODY_FIELD = 'custbody_invoiced_id';

    function doPost(params) {
        log.debug('Called from POST', params);
        if (params.payment !== null) {
            log.debug('Execution finished', 'We do not sync transactions belonging to payments');
            return;
        }
        if (params.type !== 'charge' && params.type !== 'payment') {
            log.debug('Execution finished', 'We do not sync these payment types');
            return;
        }
        let payment = record.transform({
            fromType: record.Type.CUSTOMER,
            fromId: params.customer,
            toType: record.Type.CUSTOMER_PAYMENT,
            isDynamic: true,
        });
        //initial invoices mapping
        let invoices = {};
        for (let i in params.invoices) {
            if (!params.invoices.hasOwnProperty(i)) {
                continue;
            }

            let invoice = params.invoices[i];
            invoices[invoice.id] = invoice.amount;
        }
        log.debug('Invoices', invoices);

        let applied = 0;

        payment.setValue({
            fieldId: 'total',
            value: params.amount,
            ignoreFieldChange: true,
        });
        payment.setValue({
            fieldId: 'payment',
            value: params.amount,
            ignoreFieldChange: true,
        });
        if (params.gateway && params.payment_source) {
            payment.setValue({
                fieldId: 'memo',
                value: params.gateway + ': ' + params.payment_source,
                ignoreFieldChange: true,
            });
        }
        if (params.checknum) {
            payment.setValue({
                fieldId: 'checknum',
                value: params.checknum,
                ignoreFieldChange: true,
            });
        }
        let length = payment.getLineCount({
            sublistId: 'apply',
        });
        for (let i = 0; i < length; ++i) {
            payment.selectLine({
                sublistId: 'apply',
                line: i,
            });
            let listId = payment.getCurrentSublistValue({
                sublistId: 'apply',
                fieldId: 'doc',
            });
            if (invoices[listId] === undefined) {
                continue;
            }
            let amount = invoices[listId];
            applied += amount;
            payment.setCurrentSublistValue({
                sublistId: 'apply',
                fieldId: 'amount',
                value: amount,
                ignoreFieldChange: true,
            });
            payment.setCurrentSublistValue({
                sublistId: 'apply',
                fieldId: 'apply',
                value: true,
                ignoreFieldChange: true,
            });
            payment.commitLine({
                sublistId: 'apply',
            });
        }

        payment.setValue({
            fieldId: INVOICED_ID_TRANSACTION_BODY_FIELD,
            value: params[INVOICED_ID_TRANSACTION_BODY_FIELD],
            ignoreFieldChange: true,
        });
        payment.setValue({
            fieldId: 'applied',
            value: applied,
            ignoreFieldChange: true,
        });
        payment.setValue({
            fieldId: 'unapplied',
            value: params.amount - applied,
            ignoreFieldChange: true,
        });

        let response = payment.save({
            disabletriggers: true,
            enableSourcing: true,
            ignoreMandatoryFields: true,
        });
        log.debug('Payment created', response);
        return payment;
    }

    return {
        //get:
        post: doPost,
        //put:
        //delete:
    };
});
