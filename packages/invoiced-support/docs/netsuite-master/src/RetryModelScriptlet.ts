import * as NRecord from "N/record";
import * as Log from "N/log";
import {InvoicedType, ReconciliationErrorFactoryInterface} from "./Models/ReconciliationError";

type defineCallback = (
    record: typeof NRecord,
    log: typeof Log,
    reconciliationErrorFactoryInterface: ReconciliationErrorFactoryInterface
) => object;

declare function define(arg: string[], fn: defineCallback): void;

type ReconciliationRetry = {
    id: number,
    object: InvoicedType,
}

/**
 * @NApiVersion 2.x
 * @NScriptType Restlet
 * @NModuleScope Public
 */
define([
    'N/record',
    'N/log',
    'tmp/src/Models/ReconciliationError',
], function (record,
             log,
             reconciliationError
    ) {

    class RetryModelScriptlet {
        public doPost(input: ReconciliationRetry): void {
            log.debug('Called from POST', input);
            if (!input.id) {
                throw "Unknown object id";
            }
            if (!input.object) {
                throw "Unknown object type";
            }
            let recordType = reconciliationError.toNetSuiteType(input.object);

            try {
                const object = record.load({
                    type: recordType,
                    isDynamic: true,
                    id: input.id,
                });
                object.save();
            } catch (_) {
                throw "Object not found";
            }
        }
    }


    return {
        post: (params: ReconciliationRetry) => {
            try {
                (new RetryModelScriptlet()).doPost(params);
            } catch (e: any) {
                return {
                    error: e.toString()
                };
            }
            return null;
        },
    };
});