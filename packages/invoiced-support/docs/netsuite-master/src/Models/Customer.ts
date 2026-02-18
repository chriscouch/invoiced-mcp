import {lookUpResult, NetSuiteTypeInterface} from "../definitions/NetSuiteTypeInterface";
import * as NRecord from 'N/record';
import * as Search from 'N/search';
import * as Log from 'N/log';

import {ContextExtendedInterface, ContextFactoryInterface} from '../definitions/Context';
import {AddressInterface, InvoicedAddress} from "../Utilities/Address";
import {ContactRow, CustomerRow} from "../definitions/Row";
import {CustomMappingFactoryInterface} from "./CustomMappings";
import {ConnectorFactoryInterface, Endpoint} from "./ConnectorFactory";
import {ContactFactoryInterface} from "./Contact";
import {ListFactoryInterface} from "../definitions/List";
import {NetSuiteTypeFactoryInterface} from "./NetSuiteType";
import {ContactSearchFactoryInstanceInterface} from "../Scheduled/Searches/ContactSearch";
import {SmartSearchFactoryInterface} from "../definitions/SmartSearchFactoryinterface";
import {DivisionKeyInterface} from "../Utilities/SubsidiaryKey";


export type CustomerType = "person" | "company";

export interface CustomerInterface extends NetSuiteTypeInterface {
    buildRow(): CustomerRow;
}
export interface CustomerExtendedInterface extends CustomerInterface {
    shouldSync(): boolean;
}

export interface CustomerFactoryInterface extends SmartSearchFactoryInterface {
    getInstance(context: ContextExtendedInterface): CustomerExtendedInterface;
    markForUpdate(id: number): void;
}

interface paymentTermObject extends lookUpResult {
    name: string;
}

type defineCallback = (
    search: typeof Search,
    log: typeof Log,
    global: typeof Globals,
    NetSuiteType: NetSuiteTypeFactoryInterface,
    Address: AddressInterface,
    List: ListFactoryInterface,
    CustomMappings: CustomMappingFactoryInterface,
    record: typeof NRecord,
    contactSearch: ContactSearchFactoryInstanceInterface,
    ContextFactory: ContextFactoryInterface,
    ContactFactory: ContactFactoryInterface,
    connectorFactory: ConnectorFactoryInterface,
    subsidiaryKey: DivisionKeyInterface,
) => CustomerFactoryInterface;

declare function define(arg: string[], fn: defineCallback): void;


/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define([
        'N/search',
        'N/log',
        'tmp/src/Global',
        'tmp/src/Models/NetSuiteType',
        'tmp/src/Utilities/Address',
        'tmp/src/Models/List',
        'tmp/src/Models/CustomMappings',
        'N/record',
        'tmp/src/Scheduled/Searches/ContactSearch',
        'tmp/src/ContextAdapter',
        'tmp/src/Models/Contact',
        'tmp/src/Models/ConnectorFactory',
        'tmp/src/Utilities/SubsidiaryKey',
    ],
    function(
        search,
        log,
        global,
        NetSuiteType,
        Address,
        List,
        CustomMappings,
        record,
        ContactSearchFactory,
        ContextFactory,
        ContactFactory,
        ConnectorFactory,
        SubsidiaryKey,
        ) {

        abstract class Customer extends NetSuiteType.getClass() implements CustomerExtendedInterface {
            constructor(protected data: ContextExtendedInterface) {
                super(record, search, log, ConnectorFactory, data, SubsidiaryKey);
            }

            protected getUrl(): Endpoint {
                return "customers/accounting_sync";
            }

            protected getName(): string {
                log.debug('context', this.data);
                log.debug('altname', this.getContextValueString('altname'));
                log.debug('entityid', this.getContextValueString('entityid'));
                return this.getContextValueString('altname') || this.getContextValueString('entityid') || '';
            }

            /**
             * Get customer type
             */
            protected abstract getType(): CustomerType;

            protected getPhone(): string | null {
                return this.getContextValueString('phone');
            }

            /**
             * get payment term converted to Invoiced format
             */
            private getPaymentTerm(): string|null {
                const paymentTerm: string|null = this.getPaymentTermId();
                log.debug('paymentTerm', paymentTerm);
                if (paymentTerm) {
                    const obj: paymentTermObject = search.lookupFields({
                        type: search.Type.TERM,
                        id: paymentTerm,
                        columns: ['name', ],
                    }) as paymentTermObject;
                    return Customer.transformPaymentTerm(obj.name);
                }
                return null;
            }


            private getPaymentTermId(): string|null {
                return this.getContextValueString('terms');
            }

            /**
             * Transforms payment term to
             * Invoiced format
             */
            private static transformPaymentTerm(term: string): string|null {
                return term ? term
                    .replace('Due in', 'NET')
                    .replace(' days', '')
                    .replace(' Days', '') : null;
            }

            public shouldSync(): boolean {
                return !this.data.getValueBoolean(global.INVOICED_RESTRICT_SYNC_ENTITY_FIELD);
            }

            public buildRow(): CustomerRow {
                let row: CustomerRow = super.buildRow() as CustomerRow;
                row.type = this.getType();
                const number = (this.getContextValueString('accountnumber') || (this.getContextValueString('entityid') as string)).trim();
                if (number.length > 0 && number.length <= 32) {
                    row.number = number;
                }
                row.name = this.getName();
                const email = this.getContextValueString('email');
                if (email) {
                    row.email = email;
                }
                row.active = !this.get("isinactive");

                row = this.setAddress(row);
                //this should always be after the address
                row.phone = this.getPhone();
                row.tax_id = this.getContextValueString('vatRegNumber');
                row.payment_terms = this.getPaymentTerm();

                // subsidiary
                const subsidiary: string|null = this.getSubsidiary();
                if (subsidiary) {
                    row.metadata.subsidiary = subsidiary;
                }

                row.contacts = this.buildContacts();

                const parentRecord: NRecord.Record | null = this.getParent();
                if (parentRecord) {
                    const context = ContextFactory.getInstanceExtended(parentRecord);
                    const parent = getInstanceExtended(context)
                    row.parent_customer = parent.buildRow();
                }

                // apply mapping customization
                return CustomMappings.getInstance().applyRecursive(this, row, CustomMappings.getTypes().customer);
            }


            private buildContacts(): ContactRow[] {
                const result: ContactRow[] = [];
                ContactSearchFactory.getInstance(this.getId()).run().each((item) => {
                    const nsContact = record.load({
                        type: record.Type.CONTACT,
                        id: item.id,
                        isDynamic: false,
                    });
                    const contactContext = ContextFactory.getInstanceExtended(nsContact);
                    const contact = ContactFactory.getInstance(contactContext);
                    result.push(contact.buildRow());
                    return true;
                });
                return result;
            }


            private setAddress(row: CustomerRow): CustomerRow {
                const list = List.getInstance(this.data.context, 'addressbook');
                const addresses: InvoicedAddress[] = list.map(function(list2) {
                    const defaultBilling: boolean = list2.getValueBoolean('defaultbilling');
                    if (!defaultBilling) {
                        return true;
                    }
                    //merge the address to the existing row
                    const address: NRecord.Record = list2.getSubRecord('addressbookaddress');
                    log.debug('Parsed Address', address);
                    return Address.parse(address);
                });
                log.debug('Customer Address', addresses);
                if (addresses.length > 0) {
                    const address: InvoicedAddress|undefined = addresses.shift();
                    if (!address) {
                        return row;
                    }
                    row = {
                        ...row,
                        ...address,
                    } as CustomerRow;
                }
                return row;
            }


            private getParent(): NRecord.Record | null {
                const parentId = this.getContextValueNumber('parent');
                if (!parentId) {
                    return null;
                }
                try {
                    let parent = this.record.load({
                        type: this.record.Type.CUSTOMER,
                        id: parentId,
                        isDynamic: false,
                    });
                    if (parent) {
                        return parent;
                    }
                } catch (_) {}

                return this.record.load({
                    type: this.record.Type.JOB,
                    id: parentId,
                    isDynamic: false,
                });
            }
        }

        class CustomerCompany extends Customer {
            protected getName(): string {
                return (this.getContextValueString('companyname') || super.getName()).trim();
            }

            protected getType(): CustomerType {
                return 'company';
            }
        }

        class CustomerPerson extends Customer {
            protected getName(): string {
                const firstName: string = this.getContextValueString('firstName') || '';
                const lastName: string = this.getContextValueString('lastName') || '';
                if (firstName || lastName) {
                    return [firstName, lastName, ].join(' ').trim();
                }
                return super.getName().trim();
            }

            protected getType(): CustomerType {
                return 'person';
            }
        }


        function getInstanceExtended(context: ContextExtendedInterface) {
            if (context.getType() === record.Type.JOB || context.getValueBoolean('isperson')) {
                return new CustomerPerson(context);
            }
            return new CustomerCompany(context);
        }

        return {
            getInstance:  getInstanceExtended,
            markForUpdate(id: number): void {
                record.submitFields({
                    type: record.Type.CUSTOMER,
                    id: id,
                    values: {
                        lastmodifieddate: new Date(),
                    },
                    options: {
                        enableSourcing: false,
                        ignoreMandatoryFields: true,
                    },
                });
            }
        };
    });