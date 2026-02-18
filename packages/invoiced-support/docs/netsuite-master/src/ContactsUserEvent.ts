import * as Log from "N/log";
import * as NSearch from "N/search";
import * as NRecord from "N/record";
import * as File from "N/file";
import {EntryPoints} from "N/types";
import {ContextFactoryInterface} from "./definitions/Context";
import {ContactFactoryInterface} from "./Models/Contact";
import * as ServerWidget from "N/ui/serverWidget";
import * as Url from "N/url";
import {CustomerFactoryInterface} from "./Models/Customer";

/**
 * @NApiVersion 2.x
 * @NScriptType UserEventScript
 * @NModuleScope Public
 */
type defineCallback = (
    record: typeof NRecord,
    log: typeof Log,
    search: typeof NSearch,
    file: typeof File,
    url: typeof Url,
    serverWidget: typeof ServerWidget,
    ContextAdapter: ContextFactoryInterface,
    ContactFactory: ContactFactoryInterface,
    CustomerFactory: CustomerFactoryInterface) => object;

declare function define(arg: string[], fn: defineCallback): void;

define([
    'N/record',
    'N/log',
    'N/search',
    'N/file',
    'N/url',
    'N/ui/serverWidget',
    'tmp/src/ContextAdapter',
    'tmp/src/Models/Contact',
    'tmp/src/Models/Customer',
], function(record, log, search,file, url, serverWidget, ContextAdapter, ContactFactory, CustomerFactory) {

    class ContactsUserEvent {
        markCustomers(rec: NRecord.Record): void {
            const context = ContextAdapter.getInstanceExtended(rec);
            const contact = ContactFactory.getInstance(context);
            if (!contact.shouldSync()) {
                log.debug('Skipping contact sync', 'shouldSync');
                return;
            }

            search.create({
                type: 'contact',
                columns: [search.createColumn({name: 'internalId', join: 'customer'})],
                filters: [search.createFilter({
                    name: 'internalId',
                    operator: search.Operator.IS,
                    values: contact.getId()
                })]
            }).run().each(customer => {
                log.debug('Customer to submit', customer);
                CustomerFactory.markForUpdate(customer.getValue({name: 'internalId', join: 'customer'}) as unknown as number);
                return true;
            });
        }

        afterSubmit(scriptContext: EntryPoints.UserEvent.afterSubmitContext): void {
            log.debug('Script Context', scriptContext);
            if (scriptContext.newRecord) {
                this.markCustomers(scriptContext.newRecord);
            } else if (scriptContext.oldRecord) {
                this.markCustomers(scriptContext.oldRecord);
            }
        }

        beforeLoad(scriptContext: EntryPoints.UserEvent.beforeLoadContext): void {
            if (scriptContext.newRecord.type !== record.Type.CONTACT) {
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
                    contact_id: scriptContext.newRecord.id,
                }
            });
            const fld = form.addField({
                id: 'custpage_custom_html',
                label: 'not shown - hidden',
                type: serverWidget.FieldType.INLINEHTML
            });
            fld.defaultValue = '<script>' +
                'var SUITELET_URL ="' + suiteletURL + '";' +
                file.load({id: './Assets/UpdateContacts.js'}).getContents() +
                '</script>';
        }
    }

    return {
        beforeLoad: function(scriptContext: EntryPoints.UserEvent.beforeLoadContext) {
            log.debug('Event triggered:', 'beforeLoad()');
            try {
                    (new ContactsUserEvent()).beforeLoad(scriptContext);
                } catch (e: any) {
                    log.error('Uncaught Exception - beforeLoad()', e.toString() + ' ' + (e.stack || ''));
                }
        },
        afterSubmit: function(scriptContext: EntryPoints.UserEvent.afterSubmitContext) {
            log.debug('Event triggered:', 'afterSubmit()');
            try {
                (new ContactsUserEvent()).afterSubmit(scriptContext);
            } catch (e: any) {
                log.error('Uncaught Exception - afterSubmit()', e.toString() + ' ' + (e.stack || ''));
            }
        },
    };
});