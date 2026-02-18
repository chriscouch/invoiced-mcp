import * as NRecord from "N/record";
import * as Log from "N/log";
import {EntryPoints} from "N/types";
import {CustomerFactoryInterface} from "../Models/Customer";
import {SmartSearchFactoryInterface} from "../definitions/SmartSearchFactoryinterface";
import {ContextFactoryInterface} from "../definitions/Context";
import {InvoiceFactoryInterface} from "../Models/Invoice";
import {CreditNoteFactoryInterface} from "../Models/CreditNote";
import {UserEventPaymentFactoryInterface} from "../Models/UserEventPayment";
import {ContactFactoryInterface} from "../Models/Contact";
import {ConfigFactory} from "../Utilities/Config";
import {ReconciliationErrorFactoryInterface} from "../Models/ReconciliationError";
import {DivisionKeyInterface} from "../Utilities/SubsidiaryKey";

type defineCallback = (
    record: typeof NRecord,
    log: typeof Log,
    configFactory: ConfigFactory,
    reconciliationErrorFactoryInterface: ReconciliationErrorFactoryInterface,
    ContextAdapter: ContextFactoryInterface,
    CustomerFactory: CustomerFactoryInterface,
    InvoiceFactory: InvoiceFactoryInterface,
    CreditNoteFactory: CreditNoteFactoryInterface,
    PaymentFactory: UserEventPaymentFactoryInterface,
    ContactFactory: ContactFactoryInterface,
    subsidiaryKey: DivisionKeyInterface,
) => SmartSyncInterface;

declare function define(arg: string[], fn: defineCallback): void;

export interface SmartSyncInterface {
    create(scriptContext: EntryPoints.UserEvent.afterSubmitContext): void;
}



/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define([
    'N/record',
    'N/log',
    'tmp/src/Utilities/Config',
    'tmp/src/Models/ReconciliationError',
    'tmp/src/ContextAdapter',
    'tmp/src/Models/Customer',
    'tmp/src/Models/Invoice',
    'tmp/src/Models/CreditNote',
    'tmp/src/Models/UserEventPayment',
    'tmp/src/Models/Contact',
    'tmp/src/Utilities/SubsidiaryKey',
], function (record,
             log,
             configFactory,
             ReconciliationError,
             ContextAdapter,
             CustomerFactory,
             InvoiceFactory,
             CreditNoteFactory,
             PaymentFactory,
             ContactFactory,
             SubsidiaryKey,
) {

    function create(scriptContext: EntryPoints.UserEvent.afterSubmitContext): void {
        const config = configFactory.getInstance();
        //smart sync disabled
        if (!config.doRealTimeSync()) {
            return;
        }

        if (!scriptContext.newRecord) {
            return;
        }

        //INV-586 record number is not available until record refreshed
        let nsRecord = record.load({
            type: scriptContext.newRecord.type,
            id: scriptContext.newRecord.id,
            isDynamic: true
        });

        //determine type
        let factory: SmartSearchFactoryInterface;
        switch (nsRecord.type) {
            case record.Type.CUSTOMER:
                if (!config.doSyncCustomers()) {
                    return;
                }
                factory = CustomerFactory;
                break;
            case record.Type.JOB:
                if (!config.doSyncProjects()) {
                    return;
                }
                factory = CustomerFactory;
                break;
            case record.Type.CONTACT:
                if (!config.doSyncCustomers()) {
                    return;
                }
                const contatContext = ContextAdapter.getInstanceExtended(nsRecord);
                const newRecord = ContactFactory.getInstance(contatContext);
                if (!newRecord.shouldSync()) {
                    return;
                }

                factory = CustomerFactory;
                nsRecord = record.load({
                     type: record.Type.CUSTOMER,
                     id: nsRecord.getValue({fieldId: 'company'}),
                });
                break;
            case record.Type.INVOICE:
                if (!config.doSyncInvoices()) {
                    return;
                }
                factory = InvoiceFactory;
                break;
            case record.Type.CREDIT_MEMO:
                if (!config.doSyncCreditNotes()) {
                    return;
                }
                factory = CreditNoteFactory;
                break;
            case record.Type.CUSTOMER_PAYMENT:
                if (!config.doSyncPaymentWrite()) {
                    return;
                }
                factory = PaymentFactory;
                break;
            default:
                return;
        }

        const context1 = ContextAdapter.getInstanceExtended(nsRecord);
        const newRecord = factory.getInstance(context1);
        if (!newRecord.shouldSync()) {
            log.debug('Record should not sync', scriptContext.newRecord.id);

            return;
        }

        try {
            if (!scriptContext.oldRecord) {
                log.debug('Creating Sync Queue Record (new)', scriptContext.newRecord.id);
                newRecord.send();

                return;
            }

            log.debug('Creating Sync Queue Record (updated)', scriptContext.newRecord.id);

            newRecord.send();
        } catch (e: any) {
            log.error("Error", e);
            ReconciliationError.send({
                accounting_id: nsRecord.id,
                integration_id: 2,
                message: e.toString(),
                object: ReconciliationError.toInvoiceType(nsRecord.type),
            }, SubsidiaryKey.get(nsRecord.getValue('subsidiary') as number));
        }
    }

    return {
        create: create,
    }
});
