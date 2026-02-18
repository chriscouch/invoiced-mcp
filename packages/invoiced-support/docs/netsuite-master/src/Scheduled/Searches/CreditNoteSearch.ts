/**
 * @NApiVersion 2.0
 * @NModuleScope Public
 */
import * as NSearch from "N/search";
import * as NRecord from "N/record";
import * as NLog from "N/log";
import {ConfigFactory} from "../../Utilities/Config";
import {ContextExtendedDynamicInterface, ContextFactoryInterface} from "../../definitions/Context";
import {NetSuiteTypeInterface} from "../../definitions/NetSuiteTypeInterface";
import {SearchProcessableFactoryInstanceInterface, SearchFactoryInterface, SavedCursor} from "./Search";
import {CreditNoteFactoryInterface} from "../../Models/CreditNote";
import {DateUtilitiesInterface} from "../../Utilities/DateUtilities";
import {SearchCriteriaBuilderFactory} from "../../Utilities/SearchCriteria";


type defineCallback = (
    NetSuiteSearch: typeof NSearch,
    record: typeof NRecord,
    log: typeof NLog,
    global: typeof Globals,
    Config: ConfigFactory,
    SearchFactory: SearchFactoryInterface,
    ContextFactory: ContextFactoryInterface,
    DateUtilities: DateUtilitiesInterface,
    CreditNoteFactory: CreditNoteFactoryInterface,
    SearchCriteria: SearchCriteriaBuilderFactory
) => SearchProcessableFactoryInstanceInterface;
declare function define(arg: string[], fn: defineCallback): void;

define([
    'N/search',
    'N/record',
    'N/log',
    'tmp/src/Global',
    'tmp/src/Utilities/Config',
    'tmp/src/Scheduled/Searches/Search',
    'tmp/src/ContextAdapter',
    'tmp/src/Utilities/DateUtilities',
    'tmp/src/Models/CreditNote',
    'tmp/src/Utilities/SearchCriteria',
], function (
    NetSuiteSearch,
    record,
    log,
    global,
    Config,
    SearchFactory,
    ContextFactory,
    DateUtilities,
    CreditNoteFactory,
    SearchCriteria
) {
    class CreditNoteSearch extends SearchFactory.getTransactionSearchClass() {
        constructor() {
            const config = Config.getInstance();
            const date = config.getCreditNoteCursor();
            super(record, NetSuiteSearch, log, ContextFactory, SearchCriteria, global, config, DateUtilities, date);
        }

        protected getRecordType(): NRecord.Type {
            return record.Type.CREDIT_MEMO;
        }

        protected getRecordInstance(context: ContextExtendedDynamicInterface): NetSuiteTypeInterface {
            return CreditNoteFactory.getInstance(context);
        }

        public saveCursor(id: number, cursor?: Date): void {
            log.audit("Saving Credit NoteCursor", [cursor, this.cursor]);
            Config.getInstance().set({
                custscript_invd_cn_cursor: cursor || this.cursor,
                custscript_invd_cn_id_cursor: id,
            });
        }

        getCursor(): SavedCursor {
            return {
                id: this.config.getCreditNoteIdCursor(),
                date: this.date,
            };
        }
    }


    return {
        getInstance: function () {
            return new CreditNoteSearch();
        },
    };
});
