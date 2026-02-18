import {RestletInputParameters} from "./RestletInputParameters";
import * as NRecord from "N/record";

type TransactionRestletLineItem = {
    item: number;
    rate: number;
    quantity: number;
    [key: string]: any;
    // start_date: number;
    // end_date: number;
}

export type TransactionRestletInputParameters = RestletInputParameters & {
    memo: string;
    status: string;
    items: TransactionRestletLineItem[];
    currencysymbol: string;
    tranid: string;
    trandate: number;
    duedate: number;
    voided?: boolean;
    discountrate: number;
    taxrate: number;
    discounitem: number;
    taxitem?: number;
    taxlineitem?: number;
    discountitem: number;
};

