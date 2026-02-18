/**
 * @NApiVersion 2.0
 * @NModuleScope Public
 */
import * as NSearch from "N/search";
import {ConfigFactory} from "../../Utilities/Config";
import * as NRecord from "N/record";
import * as NLog from "N/log";
import * as NRuntime from "N/runtime";
import {ContextExtendedInterface, ContextFactoryInterface} from "../../definitions/Context";
import {NetSuiteTypeInterface} from "../../definitions/NetSuiteTypeInterface";
import {CustomerFactoryInterface} from "../../Models/Customer";
import {SearchProcessableFactoryInstanceInterface, SearchFactoryInterface, SavedCursor} from "./Search";
import {DateUtilitiesInterface} from "../../Utilities/DateUtilities";
import {SearchCriteriaBuilderFactory, SearchCriteriaBuilderInterface} from "../../Utilities/SearchCriteria";


type defineCallback = (
    NetSuiteSearch: typeof NSearch,
    record: typeof NRecord,
    log: typeof NLog,
    runtime: typeof NRuntime,
    SearchFactory: SearchFactoryInterface,
    global: typeof Globals,
    Config: ConfigFactory,
    ContextFactory: ContextFactoryInterface,
    DateUtilities: DateUtilitiesInterface,
    CustomerFactory: CustomerFactoryInterface,
    SearchCriteria: SearchCriteriaBuilderFactory
) => SearchProcessableFactoryInstanceInterface;
declare function define(arg: string[], fn: defineCallback): void;

define([
    'N/search',
    'N/record',
    'N/log',
    'N/runtime',
    'tmp/src/Scheduled/Searches/Search',
    'tmp/src/Global',
    'tmp/src/Utilities/Config',
    'tmp/src/ContextAdapter',
    'tmp/src/Utilities/DateUtilities',
    'tmp/src/Models/Customer',
    'tmp/src/Utilities/SearchCriteria',
    ], function (
        NetSuiteSearch,
        record,
        log,
        runtime,
        SearchFactory,
        global,
        Config,
        ContextFactory,
        DateUtilities,
        CustomerFactory,
        SearchCriteria
) {
    class CustomerSearch extends SearchFactory.getEntitySearchClass() {
        constructor() {
            const config = Config.getInstance();
            const date = config.getCustomerCursor();
            super(record, NetSuiteSearch, log, ContextFactory, SearchCriteria, DateUtilities, global, date);
        }

        protected getRecordType(): NRecord.Type {
            return record.Type.CUSTOMER;
        }

        protected getRecordInstance(context: ContextExtendedInterface): NetSuiteTypeInterface {
            return CustomerFactory.getInstance(context)
        }

        public saveCursor(id: number, cursor?: Date): void {
            log.audit("Saving CustomerCursor", [cursor, this.cursor]);
            Config.getInstance().set({
                custscript_invd_customer_cursor: cursor || this.cursor,
                custscript_invd_customer_id_cursor: id,
            });
        }

        protected getCriteria(matchDate: boolean): SearchCriteriaBuilderInterface {
            const criteria = super.getCriteria(matchDate)
            const isAdvanced = runtime.isFeatureInEffect({
                feature: 'ADVANCEDJOBS',
            });
            const hasJobs = runtime.isFeatureInEffect({
                feature: 'JOBS',
            });
            log.debug('Is advanced', isAdvanced);
            if (!isAdvanced && hasJobs) {
                criteria.and(["isjob", this.search.Operator.IS, "F"]);
            }
            return criteria;
        }

        getCursor(): SavedCursor {
            const config = Config.getInstance();
            return {
                id: config.getCustomerIdCursor(),
                date: this.date,
            };
        }
    }


    return {
        getInstance: function () {
            return new CustomerSearch();
        },
    };
});
