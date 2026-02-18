import * as NRecord from "N/record";
import * as Log from "N/log";
import {CustomerMatcherInterface} from "./CustomerMatcher";
import {RestletInputParameters} from "../definitions/RestletInputParameters";


export type CustomerRestletInputParameters = RestletInputParameters & {
    companyname: string;
    accountnumber: string;
    currencysymbol: string;
    email: string;
    entityid: string;
    language: string;
    phone: string;
    active: boolean;
    tax_id: string;
    tax_exempt: string;
    type: 'person'|'company';
    attention_to?: string | null;
    addr1?: string | null;
    addr2?: string | null;
    city?: string | null;
    state?: string | null;
    country?: string | null;
    zip?: string | null;
};

type defineCallback = (
    record: typeof NRecord,
    log: typeof Log,
    CustomerMatcher: CustomerMatcherInterface) => SaveCustomerFactoryInterface;

declare function define(arg: string[], fn: defineCallback): void;

export interface SaveCustomerFactoryInterface {
    create(
        input: CustomerRestletInputParameters
    ): SaveCustomerInterface;
}

export interface SaveCustomerInterface {
    customer: NRecord.Record;
}



/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define([
    'N/record',
    'N/log',
    'tmp/src/Libs/CustomerMatcher',
], function (record,
             log,
             CustomerMatcher) {
    abstract class AbstractCustomer implements SaveCustomerInterface {
        protected constructor(public customer: NRecord.Record) {
        }

        public static load(id: number): NRecord.Record {
            return record.load({
                type: record.Type.CUSTOMER,
                id: id,
                isDynamic: true,
            });
        }
    }

    class UpdateCustomer extends AbstractCustomer {
        constructor(input: CustomerRestletInputParameters) {
            let customer;
            if (input.netsuite_id) {
                customer = AbstractCustomer.load(input.netsuite_id);
            }

            if (!customer) {
                throw "Customer not found";
            }

            super(customer);
        }
    }

    class CreateCustomer extends AbstractCustomer {
        constructor(input: CustomerRestletInputParameters) {
            const customerId = CustomerMatcher.getCustomerIdDecorated(
                input.accountnumber,
                input.companyname,
                input.netsuite_id,
            );
            log.debug('Customer match found', customerId);
            if (customerId) {
                super(AbstractCustomer.load(customerId));

                return;
            }

            let parentCustomerId: number | null = null;
            log.debug('Parent Customer match found', parentCustomerId);
            if (input.parent_customer) {
                parentCustomerId = CustomerMatcher.getCustomerIdDecorated(
                    input.parent_customer.accountnumber,
                    input.parent_customer.companyname,
                    input.parent_customer.netsuite_id,
                );

                log.debug('Parent Customer match found', parentCustomerId);
            }

            const customer = record.create({
                type: record.Type.CUSTOMER,
                isDynamic: true,
            });

            if (parentCustomerId) {
                customer.setValue({
                    fieldId: "parent",
                    value: parentCustomerId,
                    ignoreFieldChange: true,
                });
            }

            super(customer);
        }
    }


    return {
        create: (input: CustomerRestletInputParameters) =>
            input.netsuite_id
                ? new UpdateCustomer(input)
                : new CreateCustomer(input),
    };
});