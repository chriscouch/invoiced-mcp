import {EntryPoints} from "N/types";
import * as ServerWidget from "N/ui/serverWidget";
import * as Redirect from "N/redirect";
import * as NLog from "N/log";
import {ConfigFactory, ConfigInterface, ConfigParameters} from "./Utilities/Config";
import {SyncInterface} from "./Scheduled/Sync";
/**
 * @NApiVersion 2.x
 * @NScriptType Suitelet
 * @NModuleScope Public
 */
type defineCallback = (
    log: typeof NLog,
    serverWidget: typeof ServerWidget,
    redirect: typeof Redirect,
    Config: ConfigFactory,
    Sync: SyncInterface,
) => object;

declare function define(arg: string[], fn: defineCallback): void;

define([
    'N/log',
    'N/ui/serverWidget',
    'N/redirect',
    'tmp/src/Utilities/Config',
    'tmp/src/Scheduled/Sync',
], function(
    log, serverWidget, redirect, Config, Sync ) {
    //half of NS allowed 1,000
    const GOVERNANCE_LIMIT = 500;

    function onRequest(context: EntryPoints.Suitelet.onRequestContext): void {
        const config = Config.getInstance();
        const informationCfg = Config.getInformation();
        let offset = informationCfg.getTimeZone().match("GMT.[0-9]+:[0-9]+")?.shift();

        if (context.request.method == 'GET') {
            const form = serverWidget.createForm ({
                title : 'Invoiced Integration'
            });
            form.addFieldGroup({
                id : 'group',
                label : 'Last sync'
            });
            const lastSync = form.addField({
                id : 'last_sync',
                label : 'last_sync',
                type : serverWidget.FieldType.INLINEHTML,
                container : 'group',
            });
            lastSync.defaultValue = offset ? new Date(Math.max(
                config.getInvoiceCursor().getTime(),
                config.getProjectCursor().getTime(),
                config.getPaymentCursor().getTime(),
                config.getCreditNoteCursor().getTime(),
                config.getCustomerCursor().getTime(),
            )).toString() : "Warning, company offset timezone is not set, you would not be able to modify cursor values";
            form.clientScriptModulePath = './SyncNowClient.js';
            form.addButton({
                label : 'Sync Now',
                id : 'Sync_Now',
                functionName: "syncNow",
            });
            form.addSubmitButton({
                label : 'Save',
            });

            account(form, config);
            settings(form, config);

            context.response.writePage(form);
        } else { // POST method
            const parameters = context.request.parameters;
            log.debug("Submitted", parameters);
            if (parameters.sync) {
                Sync.execute(GOVERNANCE_LIMIT);
                return;
            }

            const toSave: ConfigParameters = {
                custscript_invd_rts: parameters.real_time_sync === 'T',
                custscript_invd_ss: parameters.scheduled_sync === 'T',
                custscript_invd_sync_customers: parameters.customer_sync === 'T',
                custscript_invd_sync_projects: parameters.project_sync === 'T',
                custscript_invd_sync_invoices: parameters.invoice_sync === 'T',
                custscript_invd_sync_credit_notes: parameters.credit_note_sync === 'T',
                custscript_invd_read_payments: parameters.payment_read === 'T',
                custscript_invd_sync_payments: parameters.payment_sync === 'T',
                custscript_invd_sync_invoices_as_drafts: parameters.sync_invoices_as_drafts === 'T',
                custscript_invd_convenience_fee: parameters.convenience_fee,
                custscript_invd_convenience_fee_tax_code: parameters.convenience_fee_tax_code,
                custscript_invd_api_key: parameters.api_key,
                custscript_invd_is_sandbox: parameters.is_sandbox === 'T',
                custscript_invd_item_dates: parameters.sync_line_items === 'T',
                custscript_invd_send_pdf: parameters.save_pdf === 'T',
                custscript_invd_dst_observed: parameters.dst !== 'F',
                custscript_invd_start_date: parameters.start_date ? new Date(parameters.start_date) : null,
            };

            config.set(toSave);
            redirect.toSuitelet({
                scriptId: 'customscript_invd_sync_now',
                deploymentId: 'customdeploy_invd_sync_now',
            });
        }
    }

    function account(form: ServerWidget.Form, config: ConfigInterface): void {
        form.addFieldGroup({
            id : 'account',
            label : 'General Settings',
        });
        addGenericField(form,{
            id : 'sync_line_items',
            label : 'Sync Line Item Dates',
            type : serverWidget.FieldType.CHECKBOX,
            container : 'account',
        }, config.syncLineItemDates() ? 'T' : 'F');
        addGenericField(form,{
            id : 'save_pdf',
            label : 'Save PDF to Invoiced',
            type : serverWidget.FieldType.CHECKBOX,
            container : 'account',
        }, config.doSendPDF() ? 'T' : 'F');
        addGenericField(form,{
            id : 'dst',
            label : 'DST observed',
            type : serverWidget.FieldType.CHECKBOX,
            container : 'account',
        }, config.isDST() ? 'T' : 'F');

        addGenericField(form,{
            id : 'is_sandbox',
            label : 'Use invoiced Sandbox',
            type : serverWidget.FieldType.CHECKBOX,
            container : 'account',
        }, config.isSandbox() ? 'T' : 'F');


        addGenericField(form,{
            id : 'real_time_sync',
            label : 'Real Time Sync',
            type : serverWidget.FieldType.CHECKBOX,
            container : 'account',
        }, config.doRealTimeSync() ? 'T' : 'F');

        addGenericField(form,{
            id : 'scheduled_sync',
            label : 'Scheduled Sync',
            type : serverWidget.FieldType.CHECKBOX,
            container : 'account',
        }, config.doScheduledSync() ? 'T' : 'F');

        addGenericField(form, {
            id : 'api_key',
            label : 'API KEY',
            type : serverWidget.FieldType.TEXT,
            container : 'account',
        }, config.getApiKey());
        addGenericField(form,{
            id : 'start_date',
            label : 'Start Date',
            type : serverWidget.FieldType.DATE,
            container : 'account',
        }, config.getStartDate() as unknown as string);


        addGenericField(form,{
            id : 'convenience_fee',
            label : 'Convenience Fee Line Item',
            type : serverWidget.FieldType.SELECT,
            container : 'account',
            source : "-10",
        },  config.getConvenienceFee() as string);
        addGenericField(form,{
            id : 'convenience_fee_tax_code',
            label : 'Convenience Fee Line Item Tax code',
            type : serverWidget.FieldType.SELECT,
            container : 'account',
            source : "-128",
        }, config.getConvenienceFeeTaxCode() as string);
    }

    function addGenericField(form: ServerWidget.Form, config: ServerWidget.AddFieldOptions, value: string) {
        const item = form.addField(config);
        item.defaultValue = value;
    }

    function settings(form: ServerWidget.Form, config: ConfigInterface): void {
        form.addFieldGroup({
            id : 'settings',
            label : 'Sync Settings',
        });
        addCheckboxField(form, config.doSyncCustomers() ? 'T' : 'F', 'customer_sync', 'Sync Customers');
        addCheckboxField(form, config.doSyncProjects() ? 'T' : 'F', 'project_sync', 'Sync Projects');
        addCheckboxField(form, config.doSyncCreditNotes() ? 'T' : 'F', 'credit_note_sync', 'Sync Credit Notes');
        addCheckboxField(form, config.doSyncInvoices() ? 'T' : 'F', 'invoice_sync', 'Sync Invoices');
        addCheckboxField(form, config.doSyncInvoiceDrafts() ? 'T' : 'F', 'sync_invoices_as_drafts', 'Sync Invoices as Drafts');
        addCheckboxField(form, config.doSyncPaymentWrite() ? 'T' : 'F', 'payment_sync', 'Write Payments to Invoiced');
        addCheckboxField(form, config.doSyncPaymentRead() ? 'T' : 'F', 'payment_read', 'Read Payments from Invoiced');
    }

    function addCheckboxField(form: ServerWidget.Form, value: string, id: string, label: string): void {
        const item = form.addField({
            id : id,
            label : label,
            type : serverWidget.FieldType.CHECKBOX,
            container : 'settings',
        });
        item.defaultValue = value;
    }


    return {
        onRequest: onRequest,
    };
});
