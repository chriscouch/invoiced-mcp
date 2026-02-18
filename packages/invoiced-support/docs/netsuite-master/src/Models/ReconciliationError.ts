import * as NRecord from "N/record";
import * as NSearch from "N/search";
import {ConnectorFactoryInterface} from "./ConnectorFactory";

type defineCallback = (
    record: typeof NRecord,
    ConnectorFactory: ConnectorFactoryInterface) => ReconciliationErrorFactoryInterface;

declare function define(arg: string[], fn: defineCallback): void;

export type InvoicedType =  "invoice" | "customer" | "payment" | "credit_note";

export type ReconciliationError = {
    "object": InvoicedType,
    "accounting_id": number,
    "message": string,
    "integration_id": number,
}
export interface ReconciliationErrorFactoryInterface {
    send(input: ReconciliationError, key: string | null): void;
    toInvoiceType(input: NSearch.Type | NRecord.Type | string): InvoicedType;
    toNetSuiteType(input: InvoicedType): NRecord.Type;
}

/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define([
        'N/record',
        'tmp/src/Models/ConnectorFactory',
    ],
    function (
        record,
        ConnectorFactory): ReconciliationErrorFactoryInterface {


        return {
            send: function(input: ReconciliationError, key: string | null): void {
                ConnectorFactory.getInstance().send("POST", "reconciliation_errors", key, input);
            },
            toInvoiceType: function(input: NSearch.Type | NRecord.Type | string): InvoicedType {
                input = input.toString();
                switch (input) {
                    case record.Type.CUSTOMER.toString():
                        return "customer";
                    case record.Type.INVOICE.toString():
                        return "invoice";
                    case record.Type.CREDIT_MEMO.toString():
                        return "credit_note";
                    case record.Type.CUSTOMER_PAYMENT.toString():
                        return "payment";
                }
                throw "Unsupported object type";
            },
            toNetSuiteType: function(input: InvoicedType): NRecord.Type {
                switch (input) {
                    case "customer":
                        return record.Type.CUSTOMER;
                    case "invoice":
                        return record.Type.INVOICE;
                    case "credit_note":
                        return record.Type.CREDIT_MEMO;
                    case "payment":
                        return record.Type.CUSTOMER_PAYMENT;
                }
                throw "Unsupported object type";
            }
        };
    });