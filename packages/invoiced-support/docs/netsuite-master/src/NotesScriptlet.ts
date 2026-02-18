import * as NRecord from "N/record";
import * as NSearch from "N/search";
import * as Log from "N/log";

/**
 * @NApiVersion 2.x
 * @NScriptType Restlet
 * @NModuleScope Public
 */

type defineCallback = (
    record: typeof NRecord,
    search: typeof NSearch,
    log: typeof Log,
) => object;

declare function define(arg: string[], fn: defineCallback): void;

type NotesScriptletInputParameters = {
    created_at: number,
    notes: string,
    customer_id: number,
    invoiced_id: number,
}

define(['N/record', 'N/search', 'N/log'], function (record, search, log) {
    function createTitle(id: number): string {
        return 'Invoiced #' + id;
    }

    function doPost(params: NotesScriptletInputParameters) {
        log.debug('Called from POST', params);

        let date = new Date(params.created_at * 1000);

        let note = record.create({
            type: record.Type.NOTE,
            isDynamic: true,
        });
        note.setValue({
            fieldId: 'note',
            value: params.notes,
        });
        note.setValue({
            fieldId: 'notedate',
            value: date,
        });
        note.setValue({
            fieldId: 'time',
            value: date,
        });
        note.setValue({
            fieldId: 'entity',
            value: params.customer_id,
        });
        note.setValue({
            fieldId: 'lastmodifieddate',
            value: new Date(),
        });
        note.setValue({
            fieldId: 'title',
            value: createTitle(params.invoiced_id),
        });
        note.save();
        log.debug('Note created', note);
        return note;
    }

    function doPut(params: NotesScriptletInputParameters) {
        log.debug('Called from Put', params);
        search.create({
            type: search.Type.NOTE,
            filters: [['title', search.Operator.IS, createTitle(params.invoiced_id)]],
            columns: ['internalId'],
        }).run().each(function (result) {
            let id = result.getValue({
                name: 'internalId',
            }) as unknown as number;

            record.submitFields({
                type: record.Type.NOTE,
                id: id,
                values: {
                    note: params.notes,
                    lastmodifieddate: new Date(),
                },
                options: {
                    enableSourcing: false,
                    ignoreMandatoryFields: true,
                },
            });
            return false;
        });
    }

    return {
        //get:
        post: doPost,
        put: doPut,
        //delete:
    };
});
