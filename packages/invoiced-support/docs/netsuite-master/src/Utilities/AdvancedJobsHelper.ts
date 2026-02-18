import * as NSearch from "N/search";
import * as Nlog from "N/log";
import * as NRuntime from "N/runtime";

export interface AdvancedJobsHelperInterface {
    getCustomerId(customerId: number): number;
}

type defineCallback = (
    search: typeof NSearch,
    log: typeof Nlog,
    runtime: typeof NRuntime,
) => AdvancedJobsHelperInterface;

declare function define(arg: string[], fn: defineCallback): void;
/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define([
    'N/search',
    'N/log',
    'N/runtime',
], function (search, log, runtime) {

    function tryJob(customerId: number): number {
        const job = search.lookupFields({
            type: search.Type.JOB,
            id: customerId,
            columns: ['customer'],
        });
        log.debug("Job found", job);
        if (!job['customer']) {
            return customerId;
        }
        log.debug('job', job['customer']);
        return parseInt(Array.isArray(job['customer']) && job['customer'][0] ?
            job['customer'][0].value : job['customer'].toString());
    }

    /**
     * Checks if customerId belongs to the customer or job
     * If later - returns parent customer id
     */
    return {
        getCustomerId(customerId: number): number {
            const isAdvanced = runtime.isFeatureInEffect({
                feature: 'ADVANCEDJOBS',
            });
            if (!isAdvanced) {
                return customerId;
            }
            const recordType = search.lookupFields({
                type: search.Type.CUSTOMER,
                id: customerId,
                columns: ['stage'],
            });
            log.debug('recordType', recordType);
            if (!recordType || Object.keys(recordType).length === 0) {
                return tryJob(customerId);
            }
            if (!recordType.stage) {
                return customerId;
            }
            const stage = recordType.stage as NSearch.LookupValueObject[];

            let type: string = stage.toString();
            if (type.toLowerCase() != "customer") {
                if (!stage.length || !stage[0]) {
                    return customerId;
                }
                type = stage[0].value.toString()
            }
            if (type.toLowerCase() !== search.Type.JOB.toString().toLowerCase()) {
                return customerId;
            }

            return tryJob(customerId);
        }
    };
});
