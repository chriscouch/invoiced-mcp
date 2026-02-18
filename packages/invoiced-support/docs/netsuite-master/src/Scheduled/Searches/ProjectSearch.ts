/**
 * @NApiVersion 2.0
 * @NModuleScope Public
 */
import * as NSearch from "N/search";
import {ConfigFactory} from "../../Utilities/Config";
import * as NRecord from "N/record";
import * as NLog from "N/log";
import {ContextExtendedInterface, ContextFactoryInterface} from "../../definitions/Context";
import {NetSuiteTypeInterface} from "../../definitions/NetSuiteTypeInterface";
import {CustomerFactoryInterface} from "../../Models/Customer";
import {SearchProcessableFactoryInstanceInterface, SearchFactoryInterface, SavedCursor} from "./Search";
import {DateUtilitiesInterface} from "../../Utilities/DateUtilities";
import {SearchCriteriaBuilderFactory} from "../../Utilities/SearchCriteria";


type defineCallback = (
    NetSuiteSearch: typeof NSearch,
    record: typeof NRecord,
    log: typeof NLog,
    SearchFactory: SearchFactoryInterface,
    Config: ConfigFactory,
    ContextFactory: ContextFactoryInterface,
    DateUtilities: DateUtilitiesInterface,
    CustomerFactory: CustomerFactoryInterface,
    global: typeof Globals,
    SearchCriteria: SearchCriteriaBuilderFactory
) => SearchProcessableFactoryInstanceInterface;
declare function define(arg: string[], fn: defineCallback): void;

define([
    'N/search',
    'N/record',
    'N/log',
    'tmp/src/Scheduled/Searches/Search',
    'tmp/src/Utilities/Config',
    'tmp/src/ContextAdapter',
    'tmp/src/Utilities/DateUtilities',
    'tmp/src/Models/Customer',
    'tmp/src/Global',
    'tmp/src/Utilities/SearchCriteria',
    ], function (
        NetSuiteSearch,
        record,
        log,
        SearchFactory,
        Config,
        ContextFactory,
        DateUtilities,
        CustomerFactory,
        global,
        SearchCriteria
) {
    class CustomerSearch extends SearchFactory.getEntitySearchClass() {
        constructor() {
            const config = Config.getInstance();
            const date = config.getProjectCursor()
            super(record, NetSuiteSearch, log, ContextFactory, SearchCriteria, DateUtilities, global, date);
        }

        protected getRecordType(): NRecord.Type {
            return record.Type.JOB;
        }

        protected getRecordInstance(context: ContextExtendedInterface): NetSuiteTypeInterface {
            return CustomerFactory.getInstance(context)
        }

        public saveCursor(id: number, cursor?: Date): void {
            log.audit("Saving Project Cursor", [cursor, this.cursor]);
            Config.getInstance().set({
                custscript_invd_project_cursor: cursor || this.cursor,
                custscript_invd_project_id_cursor: id,
            });
        }

        getCursor(): SavedCursor {
            const config = Config.getInstance();
            return {
                id: config.getProjectIdCursor(),
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
