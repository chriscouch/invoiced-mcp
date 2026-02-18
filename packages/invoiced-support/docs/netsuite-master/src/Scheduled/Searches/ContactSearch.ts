/**
 * @NApiVersion 2.0
 * @NModuleScope Public
 */
import {SearchFactoryInterface, SearchInstanceInterface} from "./Search";
import * as NSearch from "N/search";
import * as NRecord from "N/record";
import * as NLog from "N/log";
import {DateUtilitiesInterface} from "../../Utilities/DateUtilities";
import {SearchCriteriaBuilderFactory, SearchCriteriaBuilderInterface} from "../../Utilities/SearchCriteria";


type defineCallback = (
    NetSuiteSearch: typeof NSearch,
    log: typeof NLog,
    record: typeof NRecord,
    SearchFactory: SearchFactoryInterface,
    global: typeof Globals,
    DateUtilities: DateUtilitiesInterface,
    SearchCriteria: SearchCriteriaBuilderFactory
) => ContactSearchFactoryInstanceInterface;
declare function define(arg: string[], fn: defineCallback): void;


export interface ContactSearchFactoryInstanceInterface {
    getInstance(customerId: number): SearchInstanceInterface;
}

define([
    'N/search',
    'N/log',
    'N/record',
    'tmp/src/Scheduled/Searches/Search',
    'tmp/src/Global',
    'tmp/src/Utilities/DateUtilities',
    'tmp/src/Utilities/SearchCriteria',
    ], function (NetSuiteSearch, log, record, SearchFactory, global, DateUtilities, SearchCriteria) {
    class ContactSearch extends SearchFactory.getSearchClass() {
        constructor(private customerId: number) {
            super(record, NetSuiteSearch, DateUtilities, log);
        }

        protected getCriteria(): SearchCriteriaBuilderInterface {
            return SearchCriteria.getInstance().set([
                [global.IS_INVOICED_CONTACT_FIELD, NetSuiteSearch.Operator.IS, "T"],
                "AND",
                [ 'customer.internalId', NetSuiteSearch.Operator.IS, this.customerId],
            ]);
        }

        protected getRecordType(): NRecord.Type {
            return record.Type.CONTACT;
        }
    }


    return {
        getInstance: function (customerId: number) {
            return new ContactSearch(customerId);
        },
    };
});
