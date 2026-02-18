import * as Log from "N/log";
import * as NRecord from "N/record";
import {EntryPoints} from "N/types";
import {ConnectorFactoryInterface, Endpoint} from "./Models/ConnectorFactory";
import {SmartSyncInterface} from "./Libs/SmartSyncFactory";
import {DivisionKeyInterface} from "./Utilities/SubsidiaryKey";

/**
 * @NApiVersion 2.x
 * @NScriptType UserEventScript
 * @NModuleScope Public
 */
type defineCallback = (
    record: typeof NRecord,
    log: typeof Log,
    ConnectorFactory: ConnectorFactoryInterface,
    SmartSyncFactory: SmartSyncInterface,
    subsidiaryKey: DivisionKeyInterface,
) => object;

declare function define(arg: string[], fn: defineCallback): void;

define([
    'N/record',
    'N/log',
    'tmp/src/Models/ConnectorFactory',
    'tmp/src/Libs/SmartSyncFactory',
    'tmp/src/Utilities/SubsidiaryKey',
], function(record, log, ConnectorFactory, SmartSyncFactory, SubsidiaryKey) {

    class UserEvent {
        private static getUrl(context: NRecord.Record): null|Endpoint {
            switch (context.type) {
                case record.Type.CREDIT_MEMO:
                    return "credit_notes/accounting_sync";
                case record.Type.CUSTOMER_PAYMENT:
                    return "payments/accounting_sync";
                case record.Type.INVOICE:
                    return "invoices/accounting_sync";
            }

            return null;
        }


        /**
         * NetSuite User Event after submit event
         */
        afterSubmit(scriptContext: EntryPoints.UserEvent.afterSubmitContext): void {
            log.debug('Script Context', scriptContext);
            const type: string = scriptContext.type.toString();
            // Perform delete if necessary
            if (type === 'delete' && scriptContext.oldRecord) {
                const connection = ConnectorFactory.getInstance();
                const url = UserEvent.getUrl(scriptContext.oldRecord);
                if (!url) {
                    return;
                }

                connection.send('POST', url, SubsidiaryKey.get(scriptContext.oldRecord.getValue('subsidiary')?.toString() as unknown as number), {
                    accounting_id: scriptContext.oldRecord.id.toString(),
                    accounting_system: "netsuite",
                    deleted: true,
                });
                log.debug('Sync Successful', 'Deleted record on Invoiced');

                return;
            }

            SmartSyncFactory.create(scriptContext);
        }
    }
    return {
        afterSubmit: function(scriptContext: EntryPoints.UserEvent.afterSubmitContext) {
            try {
                (new UserEvent()).afterSubmit(scriptContext);
            } catch (e: any) {
                log.error('Uncaught Exception - afterSubmit()', e.toString() + ' ' + (e.stack || ''));
            }
        },
    };
});