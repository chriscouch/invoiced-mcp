import * as NRecord from 'N/record';
import * as Log from 'N/log';
import * as Search from 'N/search';
import {
    ContextExtendedInterface,
    ContextFactoryInterface
} from "../definitions/Context";
import {ListFactoryInterface} from "../definitions/List";
import {UserEventPaymentRow} from "../definitions/Row";
import {ConnectorFactoryInterface, Endpoint} from "./ConnectorFactory";
import {CustomMappingFactoryInterface} from "./CustomMappings";
import {CustomerFactoryInterface} from "./Customer";
import {NetSuiteTypeInterface} from "../definitions/NetSuiteTypeInterface";

import {NetSuiteTypeFactoryInterface} from "./NetSuiteType";
import {InvoiceFactoryInterface} from "./Invoice";
import {DivisionKeyInterface} from "../Utilities/SubsidiaryKey";

export interface UserEventPaymentInterface extends NetSuiteTypeInterface {
    buildRow(): UserEventPaymentRow | null;
}
export interface UserEventPaymentFactoryInterface {
    getInstance(context: ContextExtendedInterface): UserEventPaymentInterface;
}

type defineCallback = (
    log: typeof Log,
    search: typeof Search,
    record: typeof NRecord,
    ContextAdapter: ContextFactoryInterface,
    List: ListFactoryInterface,
    global: typeof Globals,
    ConnectorFactory: ConnectorFactoryInterface,
    CustomMappings: CustomMappingFactoryInterface,
    NetSuiteTypeFactory: NetSuiteTypeFactoryInterface,
    customerFactory: CustomerFactoryInterface,
    InvoiceFactory: InvoiceFactoryInterface,
    subsidiaryKey: DivisionKeyInterface) => UserEventPaymentFactoryInterface;

declare function define(arg: string[], fn: defineCallback): void;

/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define([
    'N/log',
    'N/search',
    'N/record',
    'tmp/src/ContextAdapter',
    'tmp/src/Models/List',
    'tmp/src/Global',
    'tmp/src/Models/ConnectorFactory',
    'tmp/src/Models/CustomMappings',
    'tmp/src/Models/NetSuiteType',
    'tmp/src/Models/Customer',
    'tmp/src/Models/Invoice',
    'tmp/src/Utilities/SubsidiaryKey',
], function (
    log,
    search,
    record,
    contextAdapter,
    list,
    global,
    ConnectorFactory,
    CustomMappings,
    NetSuiteTypeFactory,
    customerFactory,
    InvoiceFactory,
    subsidiaryKey
) {
    class UserEventPayment extends NetSuiteTypeFactory.getCustomerOwnedRecordClass() implements UserEventPaymentInterface {
        protected data: ContextExtendedInterface;
        protected getUrl(): Endpoint {
            return "payments/accounting_sync";
        }

        constructor(data: ContextExtendedInterface) {
            const customerId = data.getValue('customer');
            super(record, search, log, ConnectorFactory, data, global, contextAdapter, customerId, customerFactory, list, subsidiaryKey);
            this.data = data;
        }

        buildRow(): UserEventPaymentRow | null {
            const row = super.buildRow() as UserEventPaymentRow;
            const currency = this.getContextValueString('currencysymbol');
            if (!currency) {
                throw "Currency can't be null";
            }
            row.currency = currency.toLowerCase();
            row.voided = this.getContextValueBoolean('voided');
            row.applied_to = [];

           let unApplied = 0;
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
                    unApplied += amount;
                    return true;
                }
                const context = contextAdapter.getInstanceDynamicExtended(record.Type.INVOICE, id);
                if (context === null) {
                    return true;
                }
                const invoice = InvoiceFactory.getInstance(context)
                if (invoice.shouldSync()) {
                    row.applied_to.push({
                        amount: amount,
                        type: 'invoice',
                        invoice: invoice.buildRow(),
                    });
                }
                return true;
            });
            if (row.applied_to.length === 0 && !row.voided) {
                return null;
            }
            row.amount = (this.getContextValueNumber('applied') || 0) - unApplied;

            return CustomMappings.getInstance().applyRecursive(this, row, CustomMappings.getTypes().payment);
        }
    }

    return {
        getInstance: function(context: ContextExtendedInterface) {
            return new UserEventPayment(context);
        },
    };
});