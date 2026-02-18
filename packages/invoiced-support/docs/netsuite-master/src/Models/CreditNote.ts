/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
import * as NSearch from "N/search";
import * as Nlog from "N/log";
import * as NRecord from "N/record";
import * as NConfig from "N/config";
import {ConnectorFactoryInterface, Endpoint} from "./ConnectorFactory";
import {CustomMappingFactoryInterface} from "./CustomMappings";
import {TransactionRow, UserEventPaymentRow} from "../definitions/Row";
import {NetSuiteTypeInterface} from "../definitions/NetSuiteTypeInterface";
import {
    ContextExtendedDynamicInterface,
    ContextFactoryInterface
} from "../definitions/Context";
import {ListFactoryInterface} from "../definitions/List";
import {CustomerFactoryInterface} from "./Customer";

import {NetSuiteTypeFactoryInterface} from "./NetSuiteType";
import {InvoiceFactoryInterface} from "./Invoice";
import {ConfigFactory} from "../Utilities/Config";
import * as Render from "N/render";
import {DivisionKeyInterface} from "../Utilities/SubsidiaryKey";

export interface CreditNoteInterface extends NetSuiteTypeInterface {
    buildRow(): TransactionRow;
}

export interface CreditNoteFactoryInterface {
    getInstance(context: ContextExtendedDynamicInterface): CreditNoteInterface;
}

type defineCallback = (
    log: typeof Nlog,
    render: typeof Render,
    record: typeof NRecord,
    search: typeof NSearch,
    ConnectorFactory: ConnectorFactoryInterface,
    global: typeof Globals,
    list: ListFactoryInterface,
    config: typeof NConfig,
    CustomMappings: CustomMappingFactoryInterface,
    contextAdapter: ContextFactoryInterface,
    NetSuiteTypeFactory: NetSuiteTypeFactoryInterface,
    InvoiceFactory: InvoiceFactoryInterface,
    configFactory: ConfigFactory,
    customerFactory: CustomerFactoryInterface,
    subsidiaryKey: DivisionKeyInterface,
) => CreditNoteFactoryInterface;

declare function define(arg: string[], fn: defineCallback): void;

define([
    'N/log',
    'N/render',
    'N/record',
    'N/search',
    'tmp/src/Models/ConnectorFactory',
    'tmp/src/Global',
    'tmp/src/Models/List',
    'N/config',
    'tmp/src/Models/CustomMappings',
    'tmp/src/ContextAdapter',
    'tmp/src/Models/NetSuiteType',
    'tmp/src/Models/Invoice',
    'tmp/src/Utilities/Config',
    'tmp/src/Models/Customer',
    'tmp/src/Utilities/SubsidiaryKey',
], function (
    log,
    render,
    record,
    search,
    ConnectorFactory,
    global,
    list,
    config,
    CustomMappings,
    contextAdapter,
    NetSuiteTypeFactory,
    InvoiceFactory,
    configFactory,
    customerFactory,
    SubsidiaryKey,
) {


    class CreditNote extends NetSuiteTypeFactory.getTransactionClass() {
        constructor(data: ContextExtendedDynamicInterface, customerId: number) {
            super(record, search, log, ConnectorFactory, data, global, list, config, CustomMappings, contextAdapter, customerId, customerFactory, configFactory, render, SubsidiaryKey);
        }

        public buildRow(): TransactionRow {
            const row = super.buildRow();
            const payments: UserEventPaymentRow[] = [];
            const rowClone = {...row};

            this.list.getInstance(this.data.context, 'apply').map(list => {
                if (!list.get('apply')) {
                    return true;
                }
                const amount = list.getValueNumber('amount');
                if (!amount) {
                    return true;
                }
                const id = list.getValueNumber('internalid');
                if (!id) {
                    return true;
                }
                const context = contextAdapter.getInstanceDynamicExtended(record.Type.INVOICE, id);
                if (context === null) {
                    return true;
                }
                const invoice = InvoiceFactory.getInstance(context);
                payments.push({
                    accounting_id: list.getValueString('pymt') || (id + "_" + row.accounting_id),
                    customer: row.customer,
                    currency: row.currency,
                    applied_to: [{
                        amount: amount,
                        type: 'credit_note',
                        invoice: invoice.buildRow(),
                        credit_note: rowClone,
                        document_type: 'invoice',
                    }],
                });
                return true;
            });

            row.payments = payments;
            const file = this.getAttachment();
            if (file) {
                const attachments = this.sendAttachments([file]);
                if (attachments) {
                    row.pdf_attachment = attachments[0];
                    row.attachments = attachments;
                }
            }
            return this.customMapping.getInstance().applyRecursive(this, row, CustomMappings.getTypes().credit_note);
        }

        protected getUrl(): Endpoint {
            return "credit_notes/accounting_sync";
        }
    }
    return {
        getInstance: function(context: ContextExtendedDynamicInterface): CreditNoteInterface {
            return new CreditNote(context, context.getValue('entity'));
        },
    };
});