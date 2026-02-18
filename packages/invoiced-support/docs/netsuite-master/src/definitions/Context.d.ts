import * as NRecord from 'N/record';

interface ContextObject {
    id: number;
    type: NRecord.Type;
}

export interface CustomerOwnedRecordContextObject extends ContextObject {
    trandate: null | string;
    tranid: null | string;
}

export interface InvoiceContextObject extends CustomerOwnedRecordContextObject {
    duedate: null | string;
    memo: null | string;
    taxtotal: null | number;
}


export interface ContextInterface {
    getValue(key: string): any;
    getId(): number;
    getValueBoolean(fieldId: string): boolean;
    getValueString(fieldId: string): string;
    getType(): NRecord.Type | string;
}

export interface ContextExtendedInterface extends ContextInterface {
    context: NRecord.Record;
}
export interface ContextExtendedDynamicInterface extends ContextExtendedInterface {
    setValue(key: string, value: NRecord.FieldValue): void;
}

export interface ContextFactoryInterface {
    getInstanceSimple(context: CustomerOwnedRecordContextObject): ContextInterface;
    getInstanceExtended(context: NRecord.Record): ContextExtendedInterface;
    getInstanceDynamicExtended(type: NRecord.Type, id: number): null|ContextExtendedDynamicInterface;
}