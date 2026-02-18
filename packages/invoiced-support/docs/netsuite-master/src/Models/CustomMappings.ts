import * as Search from "N/search";
import * as Log from "N/log";
import {Row} from "../definitions/Row";
import {NetSuiteObjectInterface} from "../definitions/NetSuiteObjectInterface";
import {NetSuiteTypeInterface} from "../definitions/NetSuiteTypeInterface";

export interface CustomMappingInterface {
    applyRecursive<T extends Row>(model: NetSuiteObjectInterface, row: T, type: MappingType): T;
    mappings: Mappings,
}
export interface CustomMappingFactoryInterface {
    getInstance(): CustomMappingInterface;
    getTypes(): typeof MappingType;
}
type defineCallback = (
    search: typeof Search,
    log: typeof Log) => CustomMappingFactoryInterface;

enum MappingType {
    contact,
    credit_note,
    customer,
    invoice,
    line_item,
    payment,
    invoice_attachment,
    billable_line_item,
}

declare function define(arg: string[], fn: defineCallback): CustomMappingFactoryInterface;

type MappingEntry = {
    value: string;
    use_label: boolean;
}

type Mappings = {
    [key in keyof typeof MappingType]: Record<string,MappingEntry>;
}

/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define(['N/search', 'N/log'], function (search, log) {
    let instance: null | CustomMappingSingleton;

    /**
     * Custom mapping singleton class
     * @return {boolean}
     */
    class CustomMappingSingleton implements CustomMappingInterface {
        public mappings: Mappings = {
            contact: {},
            credit_note: {},
            customer: {},
            invoice: {},
            line_item: {},
            payment: {},
            invoice_attachment: {},
            billable_line_item: {},
        };

        constructor() {
            const that = this;
            search.create({
                type: 'customrecord_invd_mapping',
                columns: ['custrecord_invd_record_type', 'custrecord_invd_netsuite_id', 'custrecord_invd_invoiced_id', 'custrecord_inv_use_label'],
                filters: ['isinactive', search.Operator.IS, 'F'],
            })
                .run()
                .each(function (result: { getText: (arg0: { name: string; }) => any; getValue: (arg0: { name: string; }) => any; }) {
                    const recordType = result.getText({
                        name: 'custrecord_invd_record_type',
                    });
                    const netSuiteFieldId = result.getValue({
                        name: 'custrecord_invd_netsuite_id',
                    });
                    const invoicedFieldId = result.getValue({
                        name: 'custrecord_invd_invoiced_id',
                    });
                    const invoicedFieldUseLabel = result.getValue({
                        name: 'custrecord_inv_use_label',
                    });
                    const field = {
                        value: invoicedFieldId,
                        use_label: !!invoicedFieldUseLabel,
                    };

                    if (recordType === 'Contact') {
                        that.mappings.contact[netSuiteFieldId] = field;
                    } else if (recordType === 'Credit Note') {
                        that.mappings.credit_note[netSuiteFieldId] = field;
                    } else if (recordType === 'Customer') {
                        that.mappings.customer[netSuiteFieldId] = field;
                    } else if (recordType === 'Invoice') {
                        that.mappings.invoice[netSuiteFieldId] = field;
                    } else if (recordType === 'Line Item') {
                        that.mappings.line_item[netSuiteFieldId] = field;
                    } else if (recordType === 'Payment') {
                        that.mappings.payment[netSuiteFieldId] = field;
                    } else if (recordType === 'Invoice Attachment') {
                        that.mappings.invoice_attachment[netSuiteFieldId] = field;
                    } else if (recordType === 'Billable Line Item') {
                        that.mappings.billable_line_item[netSuiteFieldId] = field;
                    }
                    return true;
                });
        }

        private mergeRecursive<T extends Row>(model: NetSuiteObjectInterface, row: T, netSuiteFieldId: string, invoicedFieldIds: string[], useLabel: boolean): T {
            const key = invoicedFieldIds.shift() as keyof T;
            if (key) {
                if (invoicedFieldIds.length > 0) {
                    const childRow = typeof row[key] === 'object' ? row[key] as Row : {};
                    row[key] = this.mergeRecursive(model, childRow, netSuiteFieldId, invoicedFieldIds, useLabel) as unknown as T[keyof T];
                } else {
                    try {
                        row[key] = model.get(netSuiteFieldId) as unknown as T[keyof T];
                        if (useLabel) {
                            if (typeof model.getData !== 'undefined') {
                                const context = (model as NetSuiteTypeInterface).getData().context;
                                const fieldData = context.getField({
                                    fieldId: netSuiteFieldId
                                });
                                if (fieldData.type === 'select') {
                                    const selected = fieldData.getSelectOptions();

                                    for (const i in selected) {
                                        if (selected.hasOwnProperty(i)) {
                                            const option = selected[i];
                                            if (option && option.value === row[key]) {
                                                row[key] = option.text as unknown as T[keyof T];
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } catch (_) {}

                    if (typeof row[key] === 'string') {
                        row[key] = row[key].substring(0, 254) as T[keyof T];
                    }
                }
            }

            return row as T;
        }

        public applyRecursive<T extends Row>(model: NetSuiteObjectInterface, row: T, type: MappingType): T {
            const mappingValue = MappingType[type];
            if (!mappingValue) {
                log.debug('Custom Mappings Not Supported', mappingValue);
                return row;
            }
            let mappings = this.mappings[mappingValue as unknown as keyof typeof MappingType];
            if (!mappings) {
                log.debug('Custom Mappings Not Supported', mappings);
                return row;
            }
            log.debug(mappingValue + ' Custom Mappings', mappings);

            for (const key in mappings) {
                const value = mappings[key]?.value as string;
                const invoicedFieldIds = value.split('.');
                row = this.mergeRecursive(model, row, key, invoicedFieldIds, mappings[key]?.use_label);
            }

            return row;
        };
    }

    function getInstance() {
        if (instance == null) {
            instance = new CustomMappingSingleton();
        }
        return instance;
    }

    return {
        getInstance: getInstance,
        getTypes: () => MappingType,
    };
});
