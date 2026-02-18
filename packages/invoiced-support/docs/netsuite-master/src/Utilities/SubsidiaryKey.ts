/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
import * as Search from "N/search";
import * as NLog from "N/log";

export interface DivisionKeyInterface {
    get(subsidiaryId: number): string | null;
}

type defineCallback = (
    log: typeof NLog,
    search: typeof Search,
    global: typeof Globals) => DivisionKeyInterface;

declare function define(arg: string[], fn: defineCallback): void;

define([
    'N/log',
    'N/search',
    'tmp/src/Global',
], function(
    log,
    search,
    global
){
    return {
        get: function(subsidiaryId: number): null | string {
            log.debug('Division subsidiary id', subsidiaryId);
            const divisionKey = search.lookupFields({
                type: search.Type.SUBSIDIARY,
                id: subsidiaryId,
                columns: global.DIVISION_KEY,
            });

            log.debug('Division subsidiary key', divisionKey);
            return divisionKey[global.DIVISION_KEY]?.toString() ?? null;
        },
    };
});