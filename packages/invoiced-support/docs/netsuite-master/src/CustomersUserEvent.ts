import * as Log from "N/log";
import * as File from "N/file";
import * as NRecord from "N/record";
import {EntryPoints} from "N/types";
import * as Url from "N/url";
import * as ServerWidget from "N/ui/serverWidget";

/**
 * @NApiVersion 2.x
 * @NScriptType UserEventScript
 * @NModuleScope Public
 */
type defineCallback = (
    record: typeof NRecord,
    log: typeof Log,
    file: typeof File,
    url: typeof Url,
    serverWidget: typeof ServerWidget) => object;

declare function define(arg: string[], fn: defineCallback): void;

define([
    'N/record',
    'N/log',
    'N/file',
    'N/url',
    'N/ui/serverWidget',
], function(record, log, file, url, serverWidget) {

    class CustomersUserEvent {

        beforeLoad(scriptContext: EntryPoints.UserEvent.beforeLoadContext): void {
            if (scriptContext.newRecord.type !== record.Type.CUSTOMER) {
                return;
            }
            const form: ServerWidget.Form = scriptContext.form;
            if (!form) {
                return;
            }
            const suiteletURL = url.resolveScript({
                scriptId: 'customscript_invd_attach_contacts',
                deploymentId: 'customdeploy_invd_attach_contacts',
                returnExternalUrl: false,
                params: {
                    customer_id: scriptContext.newRecord.id,
                }
            });
            const fld = form.addField({
                id: 'custpage_custom_html',
                label: 'not shown - hidden',
                type: serverWidget.FieldType.INLINEHTML
            });
            fld.defaultValue = '<script>' +
                'var SUITELET_URL ="' + suiteletURL + '";' +
                file.load({id: './Assets/UpdateCustomers.js'}).getContents() +
                '</script>';
        }
    }

    return {
        beforeLoad: function(scriptContext: EntryPoints.UserEvent.beforeLoadContext) {
            log.debug('Event triggered:', 'beforeLoad()');
            try {
                (new CustomersUserEvent()).beforeLoad(scriptContext);
            } catch (e: any) {
                log.error('Uncaught Exception - beforeLoad()', e.toString() + ' ' + (e.stack || ''));
            }
        },
    };
});