import * as NRecord from "N/record";

type defineCallback = () => object;

declare function define(arg: string[], fn: defineCallback): void;

const invoicedAddressKeys = [
    'country',
    'attention_to',
    'address1',
    'address2',
    'state',
    'city',
    'postal_code',
    'primary',
] as const;
export type invoicedAddressKey = typeof invoicedAddressKeys[number];

const netsuiteAddressKeys = [
    'country',
    'attention',
    'addr1',
    'addr2',
    'city',
    'state',
    'zip',
    'defaultbilling',
] as const;
type netsuiteAddressKey = typeof netsuiteAddressKeys[number];


export type InvoicedAddress = {
    country?: string,
    attention_to?: string,
    address1?: string,
    address2?: string,
    city?: string,
    state?: string,
    postal_code?: number,
    primary: boolean,
};


export interface AddressInterface {
    parse(address: NRecord.Record): InvoicedAddress;
    getMapping(): Record<invoicedAddressKey, netsuiteAddressKey>;
}

/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define([], function () {

// NOTE country has a non-standard format, i.e. _unitedStates
// so it is not currently imported
    const mapping: Record<invoicedAddressKey, netsuiteAddressKey> = {
        country: 'country',
        attention_to: 'attention',
        address1: 'addr1',
        address2: 'addr2',
        city: 'city',
        state: 'state',
        postal_code: 'zip',
        primary: 'defaultbilling',
    } as const;

    /**
     * Address convertor class
     */
    return {
        // mapping: mapping,
        parse: (address: NRecord.Record): InvoicedAddress => {
            let result: InvoicedAddress = {
                primary: false,
            };

            for (let i in mapping) {
                if (!mapping.hasOwnProperty(i)) {
                    continue;
                }
                let key: invoicedAddressKey = i as invoicedAddressKey;
                // @ts-ignore
                result[key] = address.getValue(mapping[key]);
            }
            return result;
        },
        getMapping: (): Record<invoicedAddressKey, netsuiteAddressKey> => mapping,
    };
});
