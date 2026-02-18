import * as NRuntime from "N/runtime";
import * as NLog from "N/log";
import {SearchProcessableFactoryInstanceInterface, SearchProcessableInstanceInterface} from "./Searches/Search";
import {ConfigFactory} from "../Utilities/Config";
import {ReconciliationErrorFactoryInterface} from "../Models/ReconciliationError";
import {Result} from "@hitc/netsuite-types/N/search";
import {DivisionKeyInterface} from "../Utilities/SubsidiaryKey";

export interface SyncInterface {
    execute(limit: number): void;
}

type defineCallback = (
    runtime: typeof NRuntime,
    log: typeof NLog,
    configFactory: ConfigFactory,
    reconciliationErrorFactoryInterface: ReconciliationErrorFactoryInterface,
    CustomerSearchFactory: SearchProcessableFactoryInstanceInterface,
    ProjectSearchFactory: SearchProcessableFactoryInstanceInterface,
    InvoiceSearchFactory: SearchProcessableFactoryInstanceInterface,
    CreditNoteSearchFactory: SearchProcessableFactoryInstanceInterface,
    PaymentSearchFactory: SearchProcessableFactoryInstanceInterface,
    subsidiaryKey: DivisionKeyInterface,
) => SyncInterface;

declare function define(arg: string[], fn: defineCallback): void;

/**
 * @NApiVersion 2.0
 * @NModuleScope Public
 */
define([
    'N/runtime',
    'N/log',
    'tmp/src/Utilities/Config',
    'tmp/src/Models/ReconciliationError',
    'tmp/src/Scheduled/Searches/CustomerSearch',
    'tmp/src/Scheduled/Searches/ProjectSearch',
    'tmp/src/Scheduled/Searches/InvoiceSearch',
    'tmp/src/Scheduled/Searches/CreditNoteSearch',
    'tmp/src/Scheduled/Searches/PaymentSearch',
    'tmp/src/Utilities/SubsidiaryKey',
], function (
    runtime,
    log,
    ConfigFactory,
    ReconciliationError,
    CustomerSearchFactory,
    ProjectSearchFactory,
    InvoiceSearchFactory,
    CreditNoteSearchFactory,
    PaymentSearchFactory,
    SubsidiaryKey) {

    const scriptObj = runtime.getCurrentScript();

    function checkRuntime(limit: number): boolean {
        log.audit("Checking governance limit", [scriptObj.getRemainingUsage(), limit]);
        return scriptObj.getRemainingUsage() > limit;
    }

    function processItem(search: SearchProcessableInstanceInterface, item: Result, limit: number): boolean
    {
        log.audit("Processing item", [item.recordType, item.id]);
        try {
            search.processItem(item);
        } catch (e: any) {
            log.error("Error", e);
            ReconciliationError.send({
                accounting_id: parseInt(item.id),
                integration_id: 2,
                message: e.toString(),
                object: ReconciliationError.toInvoiceType(item.recordType),
            }, SubsidiaryKey.get(item.getValue('subsidiary') as unknown as number));
            const config = ConfigFactory.getInstance();
            //in case its setup error we do not continue execution
            if (e === config.getCursorError() || e === config.getStartDateError()) {
                return false;
            }
        }
        return checkRuntime(limit);
    }

    function doSearch(searchFactory: SearchProcessableFactoryInstanceInterface, limit: number): boolean {
        let doContinue: boolean = true;
        const search = searchFactory.getInstance();
        const initialCursor = new Date();
        const previousCursor = search.getCursor();

        search.run(true).each((item) => {
            if (parseInt(item.id) <= previousCursor.id) {
                log.debug("Skipping internal id:", item.id);
                return true;
            }
            log.debug("Processing internal id:", item.id);
            doContinue = processItem(search, item, limit);
            //iteration has not been finished
            if (!doContinue) {
                search.saveCursor(parseInt(item.id));
            }
            return doContinue;
        });
        if (!doContinue) {
            return false;
        }

        search.run(false).each((item) => {
            doContinue = processItem(search, item, limit);
            if (!doContinue) {
                search.saveCursor(0);
            }
            return doContinue;
        });

        //iteration has been finished
        if (doContinue) {
            search.saveCursor(0, initialCursor);
        }
        return doContinue;
    }

    function execute(limit: number): void {
        const config = ConfigFactory.getInstance();

        if (!config.doScheduledSync()) {
            log.debug("Scheduled sync is disabled, exiting", true);
            return;
        }

        if (config.doSyncCustomers()) {
            log.debug("Customer import started", true);
            const doContinue: boolean = doSearch(CustomerSearchFactory, limit);
            if (!doContinue) {
                return;
            }
        }
        if (config.doSyncProjects()) {
            log.debug("Project import started", true);
            const doContinue: boolean = doSearch(ProjectSearchFactory, limit);
            if (!doContinue) {
                return;
            }
        }
        if (config.doSyncInvoices()) {
            log.debug("Invoice import started", true);
            const doContinue: boolean = doSearch(InvoiceSearchFactory, limit);
            if (!doContinue) {
                return;
            }
        }
        if (config.doSyncCreditNotes()) {
            log.debug("CN import started", true);
            const doContinue: boolean = doSearch(CreditNoteSearchFactory, limit);
            if (!doContinue) {
                return;
            }
        }
        if (config.doSyncPaymentWrite()) {
            log.debug("Payment import started", true);
            doSearch(PaymentSearchFactory, limit);
        }

    }
    return {
        execute: execute
    }
});