import {InvoicedAddress} from "../Utilities/Address";
import {TransactionLineItem} from "../Models/NetSuiteType";

export type InvoicedRowGenericValue = string | InvoicedRowGeneric | null | number | boolean | string[] | number[];


export interface AppliedTo {
    amount: string | number,
    type: 'invoice' | 'credit_note',
    invoice: InvoiceRow,
    document_type?: string,
    credit_note?: TransactionRow,
}

export type InvoicedRowGeneric = {
    [key: string]: InvoicedRowGenericValue,
}

export type Row = {
    metadata?: InvoicedRowGeneric;
}

export type ObjectRow = Row & {
    deleted?: boolean;
    voided?: boolean;
    accounting_id: string;
    accounting_system?: 'netsuite';
}

type HasSubsidiaryRow = {
    subsidiary?: string;
}

export type HasNumberRow = ObjectRow & {
    number?: string;
}

export type CustomerRow = ObjectRow & InvoicedAddress & {
    number: string;
    name: string;
    type: string;
    phone: string | null;
    tax_id: string | null;
    payment_terms?: string | null;
    active: boolean;
    email?: string,
    metadata: HasSubsidiaryRow,
    contacts?: ContactRow[],
    parent_customer?: CustomerRow,
};

export type CustomerOwnerRecordRow = HasNumberRow & {
    date?: number;
    customer?: CustomerRow,
}

export type UserEventPaymentRow = CustomerOwnerRecordRow & {
    currency: string,
    applied_to: AppliedTo[];
    amount?: number;
}

export type TransactionRow = CustomerOwnerRecordRow & {
    calculate_taxes: boolean,
    discount: number,
    tax: number,
    items: TransactionLineItem[],
    payments: UserEventPaymentRow[],
    currency: string,
    attachments?: number[];
    pdf_attachment?: number;
}

export type InvoiceRow = TransactionRow & {
    ship_to?: InvoicedAddress;
    notes?: string;
    metadata?: HasSubsidiaryRow;
    due_date?: number;
    draft?: 1|0;
}

export type ContactRow = Row & InvoicedAddress & {
    name: string | null,
    title: string | null,
    email: string | null,
    primary: boolean,
    phone: string | null,
}