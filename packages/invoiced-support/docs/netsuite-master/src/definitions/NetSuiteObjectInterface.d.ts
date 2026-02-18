import {InvoicedRowGenericValue} from "./Row";

export interface NetSuiteObjectInterface {
    get(fieldId: string): InvoicedRowGenericValue;
}