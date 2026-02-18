import * as NRecord from "N/record";
import {RestletInputParameters} from "../definitions/RestletInputParameters";
import {TransactionRestletLineItem} from "../definitions/TransactionRestletInputParameters";
import * as Log from "N/log";

type defineCallback = (
    log: typeof Log,
) => ValueSetterInterface;

declare function define(arg: string[], fn: defineCallback): void;

export interface ValueSetterInterface {
    set(
        model: NRecord.Record,
        params: RestletInputParameters|TransactionRestletLineItem,
        restrictedFields: string[],
    ): void;
    setSublist(
        model: NRecord.Record,
        params: TransactionRestletLineItem[],
        sublistId: string,
        restrictedFields: string[],
    ): void;
}

/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define(['N/log',], function (log) {
    function set(
        model: NRecord.Record,
        params: RestletInputParameters|TransactionRestletLineItem,
        restrictedFields: string[],
    ): void {

        const fields = model.getFields();

        log.debug('Fields', fields);

        for (let i in params) {
            //strip restricted fields from data set
            if (restrictedFields.indexOf(i) !== -1) {
                continue;
            }
            if (fields.indexOf(i) !== -1) {
                log.debug('Fields to be added', {
                    fieldId: i,
                    value: params[i],
                    ignoreFieldChange: true,
                });
                try {
                    model.setValue({
                        fieldId: i,
                        value: params[i],
                        ignoreFieldChange: true,
                    });
                } catch (e) {
                    if (e.message.indexOf('Invalid date value') !== -1) {
                        model.setValue({
                            fieldId: i,
                            value: new Date(params[i] * 1000),
                            ignoreFieldChange: true,
                        });
                    } else {
                        throw e;
                    }
                }
            }
        }
    }

    function setSublist(
        model: NRecord.Record,
        items: TransactionRestletLineItem[],
        sublistId: string,
        restrictedFields: string[],
    ): void {

        const fields = model.getSublistFields({
            sublistId: sublistId,
        });

        log.debug('Fields', fields);

        for (const j in items) {
            model.selectNewLine({
                sublistId: sublistId
            });

            const params = items[j];
            for (let i in params) {
                //strip restricted fields from data set
                if (restrictedFields.indexOf(i) !== -1) {
                    continue;
                }
                if (fields.indexOf(i) !== -1) {
                    log.debug('Fields to be added', {
                        sublistId: sublistId,
                        fieldId: i,
                        value: params[i],
                    });
                    try {
                        model.setCurrentSublistValue({
                            sublistId: sublistId,
                            fieldId: i,
                            value: params[i],
                            forceSyncSourcing: true,
                        });
                    } catch (e) {
                        if (e.message.indexOf('Invalid date value') !== -1) {
                            model.setCurrentSublistValue({
                                sublistId: sublistId,
                                fieldId: i,
                                value: new Date(params[i] * 1000),
                                ignoreFieldChange: true,
                            });
                        } else {
                            throw e;
                        }
                    }
                }
            }

            model.commitLine({
                sublistId: sublistId
            });
        }
    }


    return {
        set: set,
        setSublist: setSublist,
    }
});