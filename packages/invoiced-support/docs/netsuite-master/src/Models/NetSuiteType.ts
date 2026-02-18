import * as Search from "N/search";
import * as NRecord from "N/record";
import * as Log from "N/log";
import {
    JSONResponse, NetSuiteTypeInterface,
} from "../definitions/NetSuiteTypeInterface";
import {
    ContextExtendedDynamicInterface,
    ContextFactoryInterface,
    ContextInterface
} from "../definitions/Context";
import {
    CustomerOwnerRecordRow,
    InvoicedRowGeneric,
    InvoicedRowGenericValue,
    ObjectRow, TransactionRow
} from "../definitions/Row";
import {ConnectorFactoryInterface, Endpoint} from "./ConnectorFactory";

import {CustomerFactoryInterface} from "./Customer";
import {ListFactoryInterface, ListInterface} from "../definitions/List";
import * as NConfig from "N/config";
import {CustomMappingFactoryInterface} from "./CustomMappings";
import {InvoicedTransaction} from "../definitions/InvoicedTransaction";
import * as File from "N/file";
import {FileWrapper} from "../definitions/FileWrapper";
import * as Render from 'N/render';
import {ConfigFactory, ConfigInterface} from "../Utilities/Config";
import {DivisionKeyInterface} from "../Utilities/SubsidiaryKey";

type defineCallback = () => NetSuiteTypeFactoryInterface;

declare function define(arg: string[], fn: defineCallback): typeof NetSuiteType;

abstract class NetSuiteType implements NetSuiteTypeInterface {
    protected apiError: string = "";

    protected constructor(
        protected record: typeof NRecord,
        protected search: typeof Search,
        public log: typeof Log,
        protected ConnectorFactory: ConnectorFactoryInterface,
        protected data: ContextInterface,
        protected SubsidiaryKey: DivisionKeyInterface,
    ) {
    }

    public shouldSync(): boolean {
        return true;
    }

    public getData(): ContextInterface {
        return this.data;
    }

    public getId(): number {
        return this.getData().getId();
    }

    /**
     * Wrapper for getValue function of NetSuite record object
     */
    private getContextValue(fieldId: string): null | NRecord.FieldValue {
        return this.getData().getValue(fieldId);
    }

    public getLastModifiedDate(): null | Date {
        return this.getContextValue('lastmodifieddate') as Date | null;
    }

    /**
     * Wrapper for getValue function of NetSuite record object
     */
    public get(fieldId: string): InvoicedRowGenericValue {
        const val = this.getData().getValue(fieldId);
        if (val instanceof Date) {
            return val.toString();
        }
        return val;
    }

    getContextValueString(fieldId: string): null | string {
        const value = this.getContextValue(fieldId);
        return value ? value.toString() : null;
    }

    getContextValueNumber(fieldId: string): null | number {
        const value = this.getContextValueString(fieldId);
        return value ? parseFloat(value) : null;
    }

    getContextValueBoolean(fieldId: string): boolean {
        const val = this.getContextValue(fieldId);
        return val === true || val === 'T' || val == 1;
    }

    protected abstract getUrl(): Endpoint;

    public send(): JSONResponse|null {
        const row = this.buildRow();
        if (row === null) {
            this.log.debug('Skipping save', 'Nothing has changed');
            return null;
        }
        this.log.debug('Row to be saved', row);
        const connection = this.ConnectorFactory.getInstance();
        const response = connection.send('POST', this.getUrl(), this.getDivisionKey(), row);
        if (!response) {
            this.apiError = connection.lastError;
            return null;
        }

        return response;
    }

    protected getDivisionKey(): string | null {
        const subsidiaryId: number = this.getSubsidiaryId();

        return this.SubsidiaryKey.get(subsidiaryId);
    }

    protected getSubsidiaryId(): number {
        return this.getContextValueNumber('subsidiary') as number;
    }

    /**
     * Converts NetSuite Record to the Invoiced format.
     */
    public buildRow(): ObjectRow | null {
        const row: ObjectRow = {
            accounting_system: "netsuite",
            metadata: {},
            accounting_id: this.getId().toString(),
        };
        this.log.debug('Building the row', this.data);

        return row;
    }

    protected getSubsidiary(): string | null {
        // subsidiary
        const subsidiaryId: number | null = this.getSubsidiaryId();
        this.log.debug({
            title: 'Subsidiary ID',
            details: subsidiaryId,
        });
        if (subsidiaryId) {
            try {
                const subsidiaryName: string | null = this.getContextValueString('subsidiary.name')
                if (subsidiaryName) {
                    this.log.debug('Subsidiary Name (context)', subsidiaryName);
                    return subsidiaryName;
                }
                const subsidiary = this.search.lookupFields({
                    type: this.search.Type.SUBSIDIARY,
                    id: subsidiaryId,
                    columns: ['name']
                });
                this.log.debug('Subsidiary Name (lookupFields)', subsidiary);
                if (subsidiary) {
                    return subsidiary['name'] as string;
                }
            } catch (e) {
                this.log.audit('Caught error (NetSuite Type Iteration)', e);
            }
        }

        return null;
    }
}


abstract class CustomerOwnedRecord extends NetSuiteType {
    protected constructor(
        record: typeof NRecord,
        search: typeof Search,
        log: typeof Log,
        ConnectorFactory: ConnectorFactoryInterface,
        data: ContextInterface,
        protected global: typeof Globals,
        protected contextAdapter: ContextFactoryInterface,
        protected customerId: number,
        private customerFactory: CustomerFactoryInterface,
        protected list: ListFactoryInterface,
        protected SubsidiaryKey: DivisionKeyInterface,
    ) {
        super(record, search, log, ConnectorFactory, data, SubsidiaryKey);
    }

    private getParent(): NRecord.Record {
        const parentId = this.getContextValueNumber('job');
        if (parentId) {
            const parent = this.record.load({
                type: this.record.Type.JOB,
                id: parentId,
                isDynamic: false,
            });
            if (parent) {
                return parent;
            }
        }

        try {
            return this.record.load({
                type: this.record.Type.CUSTOMER,
                id: this.customerId,
                isDynamic: false,
            });
        } catch {
            return this.record.load({
                type: this.record.Type.JOB,
                id: this.customerId,
                isDynamic: false,
            });
        }
    }


    buildRow(): CustomerOwnerRecordRow | null {
        const row = super.buildRow() as CustomerOwnerRecordRow;
        row.number = this.getContextValueString("tranid") as string;
        const parentRecord: NRecord.Record = this.getParent();
        this.log.debug("parentRecord", parentRecord);
        const context = this.contextAdapter.getInstanceExtended(parentRecord);
        const parent = this.customerFactory.getInstance(context)
        row.customer = parent.buildRow();
        const date = this.getContextValueString('trandate');
        if (date) {
            row.date = new Date(date).getTime() / 1000;
        }
        return row;
    }

    public shouldSync(): boolean {
        return !this.data.getValueBoolean(this.global.INVOICED_RESTRICT_SYNC_TRANSACTION_BODY_FIELD);
    }
}

export type TransactionLineItemMetadata = {
    quantity?: number,
    rate?: number,
    contract_total?: number,
    quantity_ordered?: number,
    start_date?: string,
    end_date?: string,
}

export type TransactionLineItem = InvoicedRowGeneric & {
    name: string,
    description?: string,
    unit_cost: number,
    quantity?: number,
    metadata?: TransactionLineItemMetadata,
}

abstract class ListDecorator {

    private list: ListInterface;

    public constructor(
        protected log: typeof Log,
        list: ListFactoryInterface,
        protected recordFactory: typeof NRecord,
        record: NRecord.Record,
    ) {
        this.list = list.getInstance(record, this.listName());
    }

    protected abstract listName(): string;

    protected abstract mappingType(customMapping: CustomMappingFactoryInterface): number;

    protected abstract itemNames(list2: ListInterface): {
        name?: string,
        description?: string
    };


    public mapItems(dates: boolean, customMapping: CustomMappingFactoryInterface): TransactionLineItem[] {
        const that  = this;
        if (!that.list.length) {
            return [];
        }
        return that.list.map((list2) => {
                this.log.debug('list', list2);
                let names = that.itemNames(list2);

                const metadata: TransactionLineItemMetadata = {};
                const item: TransactionLineItem = {
                    name: (names.name || names.description) as string,
                    quantity: 1,
                    unit_cost: list2.getValueNumber('amount') || 0,
                };

                if (item.name !== names.description && names.description) {
                    item.description = names.description;
                }

                const quantity = list2.getValueNumber('quantity');
                if (quantity) {
                    metadata.quantity = quantity;
                }
                const rate = list2.getValueNumber('rate');
                if (rate) {
                    metadata.rate = rate;
                }

                if (dates) {
                    const date1 = list2.getValueDate('custcol_atlas_contract_start_date');
                    const date2 = list2.getValueDate('custcol_atlas_contract_end_date');
                    if (date1 && date2) {
                        metadata.start_date = date1
                            .toISOString()
                            .substring(0, 10);
                        metadata.end_date = date2
                            .toISOString()
                            .substring(0, 10);
                    }
                }

                const custcol3 = list2.getValueNumber('custcol3');
                if (custcol3) {
                    metadata.contract_total = custcol3;
                }
                const quantityordered = list2.getValueNumber('quantityordered');
                if (quantityordered) {
                    metadata.quantity_ordered = quantityordered;
                }
                item.metadata = metadata;
                return customMapping.getInstance().applyRecursive(that.list, item, that.mappingType(customMapping));
            }
        );
    };
}

class ItemListDecorator extends ListDecorator {
    protected listName(): string
    {
        return 'item';
    }

    protected itemNames(list2: ListInterface): object
    {
        return {
            name: list2.getValueString('item_display') || list2.getValueString('item'),
            description: list2.getValueString('description'),
        };
    }

    protected mappingType(customMapping: CustomMappingFactoryInterface): number {
        return customMapping.getTypes().line_item;
    }
}

class BillableItemListDecorator extends ListDecorator {
    protected listName(): string
    {
        return 'itemcost';
    }

    protected itemNames(list2: ListInterface): object
    {
        const id = list2.getValueNumber('item');
        const nonInventory = list2.getValueBoolean('isnoninventory');
        const item = nonInventory ? this.recordFactory.load({
            type: this.recordFactory.Type.NON_INVENTORY_ITEM,
            id: id,
            isDynamic: false,
        }): this.recordFactory.load({
            type: this.recordFactory.Type.INVENTORY_ITEM,
            id: id,
            isDynamic: false,
        });

        return {
            name: item.getText('itemid') || item.getText('displayname'),
            description: item.getText('displayname'),
        };
    }


    protected mappingType(customMapping: CustomMappingFactoryInterface): number {
        return customMapping.getTypes().billable_line_item;
    }
}

abstract class Transaction extends CustomerOwnedRecord {
    protected configObj: ConfigInterface;

    protected constructor(
        record: typeof NRecord,
        search: typeof Search,
        log: typeof Log,
        ConnectorFactory: ConnectorFactoryInterface,
        protected data: ContextExtendedDynamicInterface,
        global: typeof Globals,
        list: ListFactoryInterface,
        protected config: typeof NConfig,
        protected customMapping: CustomMappingFactoryInterface,
        contextAdapter: ContextFactoryInterface,
        customerId: number,
        customerFactory: CustomerFactoryInterface,
        configFactory: ConfigFactory,
        protected render: typeof Render,
        protected SubsidiaryKey: DivisionKeyInterface,
    ) {
        super(record, search, log, ConnectorFactory, data, global, contextAdapter, customerId, customerFactory, list, SubsidiaryKey);
        this.configObj = configFactory.getInstance();
    }

    public buildRow() {
        const row = super.buildRow() as TransactionRow;
        row.calculate_taxes = false;
        const status = this.getContextValueString('status');
        this.log.debug('status', status);
        row.voided = status === "Voided";

        // discount
        const discount = this.getContextValueNumber('discounttotal') || 0;
        if (discount <= 0) {
            row.discount = -discount;
        }

        // tax
        const tax = (this.getContextValueNumber('taxtotal') || 0) + (this.getContextValueNumber('tax2total') || 0);
        if (tax >= 0) {
            row.tax = tax;
        }

        row.items = this.mapItems();
        const currency = this.getContextValueString('currencysymbol');
        if (!currency) {
            throw "Currency can't be null";
        }
        row.currency = currency.toLowerCase();
        return row;
    }

    public send(): JSONResponse|null {
        const data = super.send() as InvoicedTransaction | null ;
        if (data) {
            this.data.setValue(this.global.INVOICED_CLIENT_VIEW_URL_FIELD, data.url);
        }

        return data;
    }

    /**
     * Converts NetSuite Transaction line items to the Invoiced format.
     */
    private mapItems(): TransactionLineItem[] {
        this.log.debug('Transaction list iteration', 'started');

        this.log.debug('config object', this.configObj);
        const dates = this.configObj.syncLineItemDates();

        const itemList = new ItemListDecorator(
            this.log,
            this.list,
            this.record,
            this.data.context,
        );
        let result: TransactionLineItem[] = itemList.mapItems(dates, this.customMapping);

        const billableItemList = new BillableItemListDecorator(
            this.log,
            this.list,
            this.record,
            this.data.context,
        );
        result.push(...billableItemList.mapItems(dates, this.customMapping));

        if (!result.length) {
            result.push({
                name: this.getContextValueString('tranid') as string,
                unit_cost: this.getContextValueNumber('subTotal') || 0,
            });
        }

        this.log.debug('Transaction list iteration', 'finished');

        // shipping & handling
        const shippingCost = this.getContextValueNumber('shippingcost');
        this.log.debug({
            title: 'Invoice shippingCost Cost',
            details: shippingCost,
        });
        if (shippingCost) {
            result.push({
                name: 'Shipping Cost',
                unit_cost: shippingCost,
            });
        }
        const handlingCost = this.getContextValueNumber('handlingcost');
        this.log.debug({
            title: 'Invoice Handling Cost',
            details: handlingCost,
        });
        if (handlingCost) {
            result.push({
                name: 'Handling Cost',
                unit_cost: handlingCost,
            });
        }

        return result;
    }

    protected getAttachment(): FileWrapper | null {
        if (this.configObj.doSendPDF()) {
            try {
                const transactionFile: File.File = this.render.transaction({
                    entityId: this.getId(),
                    printMode: this.render.PrintMode.PDF,
                    inCustLocale: true,
                });
                return {
                    value: transactionFile,
                    name: 'file',
                };
            } catch (e: any) {
                this.log.audit("Error while creating invoice PDF", (e.message || e.toString()) + (e.getStackTrace ? ' \n \n' + e.getStackTrace().join(' \n') : ''));
            }
        }
        return null;
    }

    protected sendAttachments(files: FileWrapper[]): number[] {
        const attachments: number[] = [];
        for (const i in files) {
            if (!files.hasOwnProperty(i)) {
                continue;
            }
            const file = files[i];
            if (!file) {
                continue;
            }
            try {
                const connection = this.ConnectorFactory.getInstance();
                const response = connection.sendFile([file, ], this.getDivisionKey());
                this.log.debug("File sent", response);
                if (response && response.id) {
                    attachments.push(response.id);
                }
            } catch (e: any) {
                this.log.audit("Error while sending a file", (e.message || e.toString()) + (e.getStackTrace ? ' \n \n' + e.getStackTrace().join(' \n') : ''));
            }
        }

        return attachments;
    }
}

/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
export interface NetSuiteTypeFactoryInterface {
    getClass(): typeof NetSuiteType;
    getCustomerOwnedRecordClass(): typeof CustomerOwnedRecord;
    getTransactionClass(): typeof Transaction;
}

define([], function (
) {
    return {
        getClass: function () {
            return NetSuiteType;
        },
        getCustomerOwnedRecordClass: function () {
            return CustomerOwnedRecord;
        },
        getTransactionClass: function (): typeof Transaction {
            return Transaction;
        },
    };
});
