import * as Search from 'N/search';
import * as Render from 'N/render';
import * as NRecord from 'N/record';
import * as Config from 'N/config';
import * as Log from 'N/log';
import * as File from 'N/file';
import {NetSuiteTypeInterface} from "../definitions/NetSuiteTypeInterface";
import {ConnectorFactoryInterface, Endpoint} from "./ConnectorFactory";
import {FileWrapper} from "../definitions/FileWrapper";
import {AddressInterface, InvoicedAddress} from "../Utilities/Address";
import {
    ContextExtendedDynamicInterface,
    ContextFactoryInterface
} from "../definitions/Context";
import {CustomMappingFactoryInterface} from "./CustomMappings";
import {InvoiceRow} from "../definitions/Row";
import {ListFactoryInterface} from "../definitions/List";
import {ConfigFactory} from "../Utilities/Config";
import {CustomerFactoryInterface} from "./Customer";

import {NetSuiteTypeFactoryInterface} from "./NetSuiteType";
import {DivisionKeyInterface} from "../Utilities/SubsidiaryKey";

type defineCallback = (
    search: typeof Search,
    render: typeof Render,
    record: typeof NRecord,
    config: typeof Config,
    file: typeof File,
    log: typeof Log,
    configFactory: ConfigFactory,
    NetSuiteTypeFactory: NetSuiteTypeFactoryInterface,
    Address: AddressInterface,
    global: typeof Globals,
    ConnectorFactory: ConnectorFactoryInterface,
    list: ListFactoryInterface,
    CustomMappings: CustomMappingFactoryInterface,
    contextAdapter: ContextFactoryInterface,
    customerFactory: CustomerFactoryInterface,
    subsidiaryKey: DivisionKeyInterface) => InvoiceFactoryInterface;

declare function define(arg: string[], fn: defineCallback): void;


export interface InvoiceInterface extends NetSuiteTypeInterface {
    buildRow(): InvoiceRow;
    shouldSync(): boolean;
}

export interface InvoiceFactoryInterface {
    getInstance(context: ContextExtendedDynamicInterface): InvoiceInterface;
}

type InvoicedInvoiceAddress = InvoicedAddress & {
    name?: string,
};

/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define([
        'N/search',
        'N/render',
        'N/record',
        'N/config',
        'N/file',
        'N/log',
        'tmp/src/Utilities/Config',
        'tmp/src/Models/NetSuiteType',
        'tmp/src/Utilities/Address',
        'tmp/src/Global',
        'tmp/src/Models/ConnectorFactory',
        'tmp/src/Models/List',
        'tmp/src/Models/CustomMappings',
        'tmp/src/ContextAdapter',
        'tmp/src/Models/Customer',
        'tmp/src/Utilities/SubsidiaryKey',
    ],
    function (
        search,
        render,
        record,
        config,
        nFile,
        log,
        configFactory,
        NetSuiteTypeFactory,
        Address,
        global,
        ConnectorFactory,
        list,
        CustomMappings,
        contextAdapter,
        customerFactory,
        SubsidiaryKey): InvoiceFactoryInterface {


        class Invoice extends NetSuiteTypeFactory.getTransactionClass() implements NetSuiteTypeInterface {
            constructor(data: ContextExtendedDynamicInterface, customerId: number) {
                super(record, search, log, ConnectorFactory, data, global, list, config, CustomMappings, contextAdapter, customerId, customerFactory, configFactory, render, SubsidiaryKey);
            }


            buildRow(): InvoiceRow {
                const row: InvoiceRow = super.buildRow();
                const date = this.getContextValueString('duedate');
                if (date) {
                    row.due_date = new Date(date).getTime() / 1000;
                }
                const attachments = this.createAttachments();
                if (attachments.length) {
                    row.attachments = attachments;
                    row.pdf_attachment = attachments[0];
                }
                const shipping = this.getShipping();
                if (shipping) {
                    row.ship_to = shipping;
                }
                // notes
                const notes = this.getContextValueString('memo');
                log.debug('Invoice notes', notes);
                if (notes) {
                    row.notes = notes;
                }
                const subsidiary: string|null = this.getSubsidiary();
                if (subsidiary) {
                    if (!row.metadata) {
                        row.metadata = {};
                    }
                    row.metadata.subsidiary = subsidiary;
                }

                if (this.configObj.doSyncInvoiceDrafts()) {
                    row.draft = 1;
                }
                // apply mapping customization
                return this.customMapping.getInstance().applyRecursive(this, row, CustomMappings.getTypes().invoice);
            }

            protected getUrl(): Endpoint {
                return "invoices/accounting_sync";
            }

            private createAttachments(): number[] {
                const files: FileWrapper[] = [];
                const file = this.getAttachment();
                if (file) {
                    files.push(file);
                }
                const mappings = this.customMapping.getInstance();
                for (const i in mappings.mappings.invoice_attachment) {
                    if (!mappings.mappings.invoice_attachment.hasOwnProperty(i)) {
                        continue;
                    }
                    try {
                        const fileId = this.getContextValueNumber(i);
                        log.debug("Invoice file id", fileId);
                        if (!fileId) {
                            continue;
                        }
                        const file: File.File = nFile.load({id: fileId, });
                        files.push({
                            value: file,
                            name: 'file',
                        });
                    } catch (e: any) {
                        log.audit("Error fetching a file from file cabinet", i + ' ' + (e.message || e.toString()) + (e.getStackTrace ? ' \n \n' + e.getStackTrace().join(' \n') : ''));
                    }
                }
                log.debug("Invoice files", files);

                return this.sendAttachments(files);
            }
            private getShipping(): null | InvoicedAddress {
                const context: NRecord.Record = this.data.context;
                // ship to address
                log.debug("Build row context", context);
                log.debug("Build row context", context.getValue({
                    fieldId: 'internalId',
                }));
                const address: NRecord.Record = context.getSubrecord({
                    fieldId: 'shippingaddress',
                });
                log.debug('Invoice shipping address', address);
                if (address) {
                    const name = address.getValue('addressee') as string | null;
                    if (name) {
                        const parsedAddress = Address.parse(address) as InvoicedInvoiceAddress;
                        parsedAddress.name = name;

                        return parsedAddress;
                    }
                }

                return null;
            }
        }

        return {
            getInstance: function(context: ContextExtendedDynamicInterface): InvoiceInterface {
                return new Invoice(context, context.getValue('entity'));
            },
        };
    });