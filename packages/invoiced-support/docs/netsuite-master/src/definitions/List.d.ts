import {NetSuiteObjectInterface} from "./NetSuiteObjectInterface";
import * as NRecord from "N/record";
import {FieldValue, Record} from "N/record";

export type listCallback<T extends ListInterface> = (list: T) => any;

export interface ListFactoryInterface {
    getInstance(context: Record, listId: string): ListInterface;
}

export interface ListInterface extends NetSuiteObjectInterface {
    length: number;
    getValueString(fieldId: string): null | string;
    getValueNumber(fieldId: string): null | number
    getValueDate(fieldId: string): null | Date;
    map(callback: listCallback<ListInterface>): any[];
    getSubRecord(name: string): NRecord.Record;
    getValueBoolean(key: string): boolean;
    set(fieldId: string, value: FieldValue, ignoreFieldChange?: boolean): void;
    commitLine(): void;
    getItem(fieldId: string): FieldValue;
}
