import * as NRecord from "N/record";
import {ContextExtendedInterface, ContextInterface} from "../definitions/Context";
import {AddressInterface, InvoicedAddress} from "../Utilities/Address";
import {CustomMappingFactoryInterface} from "./CustomMappings";
import {ListFactoryInterface, ListInterface} from "../definitions/List";
import {ContactRow, InvoicedRowGenericValue} from "../definitions/Row";
import {NetSuiteTypeSimplifiedInterface} from "../definitions/NetSuiteTypeInterface";


/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */

interface ContactInterface extends NetSuiteTypeSimplifiedInterface {
    buildRow(): ContactRow;
}

export interface ContactFactoryInterface {
    getInstance(context: ContextInterface): ContactInterface;
}

type defineCallback = (
    list: ListFactoryInterface,
    Address: AddressInterface,
    CustomMappings: CustomMappingFactoryInterface,
    global: typeof Globals
    ) => ContactFactoryInterface;

declare function define(arg: string[], fn: defineCallback): void;


define([
    'tmp/src/Models/List',
    'tmp/src/Utilities/Address',
    'tmp/src/Models/CustomMappings',
    'tmp/src/Global',
], function (List, uAddress, CustomMappings, global) {
    function findPrimaryAddress(array: InvoicedAddress[]): InvoicedAddress | null {
        for (let i = 0; i < array.length; i++) {
            const obj: InvoicedAddress = array[i] as InvoicedAddress;
            if (obj.primary) {
                return obj;
            }
        }
        return null;
    }

    class Contact implements ContactInterface {
        protected data: ContextExtendedInterface;

        constructor(context: ContextExtendedInterface) {
            this.data = context;
        }

        public get(fieldId: string): InvoicedRowGenericValue {
            return this.data.getValue(fieldId);
        }

        public buildRow(): ContactRow {
            let row: ContactRow = {
                name: this.data.getValueString('entityid'),
                title: this.data.getValueString('title'),
                email: this.data.getValueString('email'),
                primary: false,
                phone:
                    this.data.getValueString('phone') ||
                    this.data.getValueString('mobilephone') ||
                    this.data.getValueString('officephone') ||
                    this.data.getValueString('homephone'),
            };

            // address
            row = this.setAddress(row);

            // apply mapping customization
            row = CustomMappings.getInstance().applyRecursive(this, row, CustomMappings.getTypes().contact);

            return row;
        }

        private setAddress(row: ContactRow): ContactRow {
            //merge the address to the existing row
            const list = List.getInstance(this.data.context, 'addressbook');
            //if we have some addresses - merge the billing or first address into contact row
            if (list.length) {
                const addresses: InvoicedAddress[] = list.map((list: ListInterface): InvoicedAddress => {
                    const address: NRecord.Record = list.getSubRecord('addressbookaddress');
                    return uAddress.parse(address);
                });
                let address = findPrimaryAddress(addresses);
                if (address) {
                    row.primary = true;
                } else {
                    address = addresses[0] as InvoicedAddress;
                }
                if (address) {
                    row.address1 = address.address1;
                    row.address2 = address.address2;
                    row.city = address.city;
                    row.state = address.state;
                    row.postal_code = address.postal_code;
                }
            }
            return row;
        }

        getId(): number {
            return this.data.getId();
        }

        shouldSync(): boolean {
            return this.data.getValueBoolean(global.IS_INVOICED_CONTACT_FIELD);
        }
    }

    return {
        getInstance: function (context: ContextExtendedInterface): ContactInterface {
            return new Contact(context);
        },
    }
});
