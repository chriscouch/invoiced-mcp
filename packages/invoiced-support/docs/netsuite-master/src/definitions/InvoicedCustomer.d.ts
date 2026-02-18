import {InvoicedAddress} from "../Utilities/Address";
import {InvoicedMetadata} from "./InvoicedMetadata";


export type InvoicedCustomer = InvoicedAddress & {
    payment_terms?: string;
    active: boolean;
    taxId?: string;
    phone?: string;
    type: string;
    number: string,
    id: number,
    name: string,
    metadata: InvoicedMetadata,
}