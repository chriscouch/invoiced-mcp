/**
 * @NApiVersion 2.0
 * @NModuleScope Public
 */
import * as NSearch from "N/search";
import * as NRecord from "N/record";
import * as NLog from "N/log";
import {ContextExtendedDynamicInterface, ContextFactoryInterface} from "../../definitions/Context";
import {NetSuiteTypeInterface} from "../../definitions/NetSuiteTypeInterface";
import {ConfigInterface} from "../../Utilities/Config";
import {DateUtilitiesInterface} from "../../Utilities/DateUtilities";
import {
    SearchCriteriaBuilderFactory,
    SearchCriteriaBuilderInterface
} from "../../Utilities/SearchCriteria";

const INVOICED_ROLE_NAME: string = 'Invoiced Integration';
const INVOICED_ROLE: string = 'customrole_invd_integration';

export interface SearchInstanceInterface {
    run(matchDate?: boolean): NSearch.ResultSet;
}

export interface SearchFactoryInterface {
    getSearchClass(): typeof Search;
    getSearchProcessableClass(): typeof SearchProcessable;
    getTransactionSearchClass(): typeof TransactionSearch;
    getEntitySearchClass(): typeof EntitySearch;
}

enum Months {
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"
}

type defineCallback = () => SearchFactoryInterface;

declare function define(arg: string[], fn: defineCallback): typeof Search;

abstract class Search implements SearchInstanceInterface {
    protected constructor(
        protected record: typeof NRecord,
        protected search: typeof NSearch,
        protected DateUtilities: DateUtilitiesInterface,
        protected log: typeof NLog) {
    }

    public run(matchDate: boolean): NSearch.ResultSet {
        const searchParameters: NSearch.SearchCreateOptions = {
            type: this.getRecordType().toString(),
            filters: this.getCriteria(matchDate).get(),
            columns: this.getColumns(matchDate),
        };
        this.log.audit('Search Parameters', searchParameters);
        return this.search.create(searchParameters).run();
    }

    protected getColumns(_: boolean): NSearch.Column[] {
        return [this.buildColumn('subsidiary')];
    }

    protected abstract getRecordType(): NRecord.Type;

    protected abstract getCriteria(matchDate: boolean): SearchCriteriaBuilderInterface;

    protected buildColumn(name: string, join?: string, sort?: NSearch.Sort): NSearch.Column {
        const options: NSearch.CreateSearchColumnOptions = {
            name: name,
        };
        if (join) {
            options.join = join;
        }
        if (sort) {
            options.sort = sort;
        }
        return this.search.createColumn(options);
    }
}

export type SavedCursor = {
    id: number,
    date: null|Date,
}

export interface SearchProcessableInstanceInterface extends SearchInstanceInterface {
    processItem(item: NSearch.Result): void;
    saveCursor(idCursor: number, date?: Date): void;
    getCursor(): SavedCursor;
}

export interface SearchProcessableFactoryInstanceInterface {
    getInstance(): SearchProcessableInstanceInterface;
}


abstract class SearchProcessable extends Search implements SearchProcessableInstanceInterface {
    protected cursor: Date;
    private readonly searchCriteria: SearchCriteriaBuilderInterface;
    protected constructor(
        record: typeof NRecord,
        search: typeof NSearch,
        log: typeof NLog,
        private contextFactory: ContextFactoryInterface,
        searchCriteriaBuilderFactory: SearchCriteriaBuilderFactory,
        DateUtilities: DateUtilitiesInterface,
        protected readonly date: Date | null= null) {
        super(record, search, DateUtilities, log);
        this.searchCriteria = searchCriteriaBuilderFactory.getInstance();
        this.cursor = date || new Date();
    }

    public processItem(item: NSearch.Result): void {
        const context = this.contextFactory.getInstanceDynamicExtended(this.getRecordType(), parseInt(item.id));
        if (context === null) {
            return;
        }
        const obj = this.getRecordInstance(context);
        const date = obj.getLastModifiedDate();
        this.log.audit("Item last modified:", date);
        if (obj.send()) {
            if (date) {
                this.cursor = date;
            }
        }
    }

    protected abstract getRecordType(): NRecord.Type;

    protected abstract getRecordInstance(context: ContextExtendedDynamicInterface): NetSuiteTypeInterface;

    public abstract saveCursor(id: number, date?: Date): void;

    public abstract getCursor(): SavedCursor;

    protected getColumns(matchDate: boolean): NSearch.Column[]  {
        const columns = super.getColumns(matchDate);

        columns.push(matchDate
            ? this.buildColumn('internalId', undefined, this.search.Sort.ASC)
            : this.buildColumn('lastmodifieddate', undefined, this.search.Sort.ASC)
        );

        return columns;
    }

    protected lastModifiedString(d: Date, dateOnly: boolean): string {
        const format = this.DateUtilities.getFormat(dateOnly);
        d = this.DateUtilities.getCompanyDate(d.getTime() / 1000, true);
        this.log.debug('Last Modified time (Company)', [d, d.getUTCHours(), d.getHours(), format])
        let hour = d.getUTCHours();
        const amOrPm = (hour < 12) ? "AM" : "PM";
        hour = hour % 12;
        hour = hour ? hour : 12;
        //toLocaleString doesn't work with NS
        const formattedDate = format
            .replace("DD", SearchProcessable.padStart(d.getUTCDate()))
            .replace("D", d.getUTCDate().toString())
            .replace("YYYY", d.getUTCFullYear().toString())
            .replace("Mon", Months[d.getUTCMonth()]!.toString().substring(0, 3))
            .replace("MONTH", Months[d.getUTCMonth()]!.toString())
            .replace("MM", SearchProcessable.padStart(d.getUTCMonth() + 1))
            //to avoid replacing "M" in Months like Mar, May etc
            .replace(/M([^a-z])/, String(d.getUTCMonth() + 1) + "$1")
            .replace("H", d.getUTCHours().toString())
            //to avoid replacing "h" in Months like March
            .replace(" h", " " + hour.toString())
            .replace("mm", SearchProcessable.padStart(d.getUTCMinutes()))
            //to avoid replacing "a" in Months like Jan Mar, May etc
            .replace(" a", " " + amOrPm);
        this.log.debug('FormattedDate', formattedDate);
        return formattedDate;
    }

    //padStart doesnt work in NS
    private static padStart(input: number): string {
        const inputString = input.toString();
        return inputString.length === 2 ? inputString : '0' + inputString;
    }

    protected getCriteria(matchDate: boolean): SearchCriteriaBuilderInterface {
        this.log.debug("Last Modified:", this.date);
        if (!this.date) {
            return this.searchCriteria;
        }
        this.date.setSeconds(0);
        //plus one minute
        const time2 = new Date(this.date.getTime() + 60000);
        const date = this.lastModifiedString(this.date, false);
        const date2 = this.lastModifiedString(time2, false);
        this.log.debug("Last Modified (string):", date);

        this.searchCriteria.set(matchDate ?
            [
                ['lastmodifieddate', this.search.Operator.NOTBEFORE, date],
                "AND",
                ['lastmodifieddate', this.search.Operator.NOTONORAFTER, date2],
            ] : [
                ['lastmodifieddate', this.search.Operator.ONORAFTER, date2]
            ],
        );


        var roleSearch = this.search.create({ type: this.search.Type.ROLE, columns: [], filters: ['name', this.search.Operator.IS, INVOICED_ROLE_NAME] });
        var roleSearchSet = roleSearch.run();
        var resultRange = roleSearchSet.getRange({ start: 0, end: 50 });
        for (var i = 0; i < resultRange.length; i++) {
            var role = this.record.load({type: 'role', id: resultRange[i]?.id ?? 0});
            if (role.getValue('scriptid') !== INVOICED_ROLE) {
                continue;
            }
            if ("SELECTED" === role.getValue('subsidiaryoption')) {
                const subsidiaries = role.getValue('subsidiaryrestriction');
                this.log.audit('Subsidiaries applied', subsidiaries);
                this.searchCriteria.and(['subsidiary', this.search.Operator.ANYOF, subsidiaries]);
            }
        }

        return this.searchCriteria;
    }
}

abstract class TransactionSearch extends SearchProcessable {
    protected constructor(
        record: typeof NRecord,
        search: typeof NSearch,
        log: typeof NLog,
        contextFactory: ContextFactoryInterface,
        searchCriteriaBuilderFactory: SearchCriteriaBuilderFactory,
        private global: typeof Globals,
        protected config: ConfigInterface,
        DateUtilities: DateUtilitiesInterface,
        date: null | Date) {
        super(record, search, log, contextFactory, searchCriteriaBuilderFactory, DateUtilities, date);
    }

    protected getCriteria(matchDate: boolean): SearchCriteriaBuilderInterface {
        const criteria = super.getCriteria(matchDate)
        criteria.and(['mainline', this.search.Operator.IS, 'T'])
            .and([this.global.INVOICED_RESTRICT_SYNC_TRANSACTION_BODY_FIELD, this.search.Operator.IS, 'F'])
            .and(['number', this.search.Operator.DOESNOTCONTAIN, 'Memorized'])
            .and(['memorized', this.search.Operator.IS, false]);
        const startDate = this.config.getStartDate();
        if (startDate) {
            criteria.and(['trandate', this.search.Operator.ONORAFTER, this.lastModifiedString(startDate, true)]);
        }
        return criteria;
    }
}


abstract class EntitySearch extends SearchProcessable {
    protected constructor(
        record: typeof NRecord,
        search: typeof NSearch,
        log: typeof NLog,
        contextFactory: ContextFactoryInterface,
        searchCriteriaBuilderFactory: SearchCriteriaBuilderFactory,
        DateUtilities: DateUtilitiesInterface,
        private global: typeof Globals,
        date: null | Date) {
        super(record, search, log, contextFactory, searchCriteriaBuilderFactory, DateUtilities, date);
    }

    protected getCriteria(matchDate: boolean): SearchCriteriaBuilderInterface {
        return super.getCriteria(matchDate).and([this.global.INVOICED_RESTRICT_SYNC_ENTITY_FIELD, this.search.Operator.IS, "F"]);
    }
}

/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */

define([], function (
) {
    return {
        getSearchClass: function () {
            return Search;
        },
        getSearchProcessableClass: function () {
            return SearchProcessable;
        },
        getTransactionSearchClass: function () {
            return TransactionSearch;
        },
        getEntitySearchClass: function () {
            return EntitySearch;
        },
    };
});
