import {expect} from "@jest/globals";

const {
    loadSuiteScriptModule,
    NSearch,
    NRecord,
    NLog,
} = require('netsumo');
import {SavedCursor, SearchFactoryInterface} from "../../../../src/Scheduled/Searches/Search";
import {ContextExtendedDynamicInterface, ContextFactoryInterface} from "../../../../src/definitions/Context";
import {NetSuiteTypeInterface} from "../../../../src/definitions/NetSuiteTypeInterface";
import {Type} from "N/record";
import {SearchCriteriaBuilderFactory} from "../../../../src/Utilities/SearchCriteria";

const dateUtilsMock = {
    getCompanyDate: () => new Date(Date.UTC(2018, 2, 5, 15, 8, 12)),
    getFormat: jest.fn(),
};


const Search = loadSuiteScriptModule('tmp/src/Scheduled/Searches/Search.js');
export const search: SearchFactoryInterface = Search({});
class SearchTest extends search.getSearchProcessableClass() {
    constructor() {
        super(
            {} as typeof NRecord,
            {} as typeof NSearch,
            {
                debug: jest.fn(),
            } as typeof NLog,
            {} as ContextFactoryInterface,
            {
                getInstance: jest.fn(),
            } as SearchCriteriaBuilderFactory,
            dateUtilsMock,
            );
    }

    protected getRecordInstance(_: ContextExtendedDynamicInterface): NetSuiteTypeInterface {
        return {} as NetSuiteTypeInterface;
    }

    protected getRecordType(): Type {
        return Search.Type.CUSTOMER;
    }

    saveCursor(_: number, __?: Date): void {
    }

    lastModifiedString(d: Date, dateOnly: boolean): string {
        return super.lastModifiedString(d, dateOnly);
    }

    getCursor(): SavedCursor {
        return {
            id: 0,
            date: null,
        }
    }
}
const searchTest = new SearchTest();

const DateFormats: Record<string, string> = {
    "M/D/YYYY": "3/5/2018",
    "D/M/YYYY": "5/3/2018",
    "D-Mon-YYYY": "5-Mar-2018",
    "D.M.YYYY": "5.3.2018",
    "D-MONTH-YYYY": "5-March-2018",
    "D MONTH, YYYY": "5 March, 2018",
    "YYYY/M/D": "2018/3/5",
    "YYYY-M-D": "2018-3-5",
    "DD/MM/YYYY": "05/03/2018",
    "DD-Mon-YYYY": "05-Mar-2018",
    "DD.MM.YYYY": "05.03.2018",
    "DD-MONTH-YYYY": "05-March-2018",
    "DD MONTH, YYYY": "05 March, 2018",
    "MM/DD/YYYY": "03/05/2018",
    "YYYY/MM/DD": "2018/03/05",
    "YYYY-MM-DD":"2018-03-05"
}

const TimeFormats: Record<string, string> = {
    "h:mm a": "3:08 PM",
    "H:mm": "15:08",
    "h-mm a": "3-08 PM",
    "H-mm": "15-08",
}

describe('date formats', () => {
    test('datetime formats', () => {
        for (const date in DateFormats) {
            for (const time in TimeFormats) {
                dateUtilsMock.getFormat.mockReturnValueOnce(date + " " + time);
                expect(searchTest.lastModifiedString(new Date(), false)).toEqual(DateFormats[date] + " " + TimeFormats[time]);
            }
        }
    });
    test('date formats', () => {
        for (const date in DateFormats) {
            dateUtilsMock.getFormat.mockReturnValueOnce(date);
            expect(searchTest.lastModifiedString(new Date(), true)).toEqual(DateFormats[date]);
        }
    });
    test('12AM/PM', () => {
        dateUtilsMock.getFormat.mockReturnValue("M/D/YYYY h:mm a");

        dateUtilsMock.getCompanyDate = () => new Date("2023-05-29T12:04:26.000Z");
        expect(searchTest.lastModifiedString(new Date(), false)).toEqual("5/29/2023 12:04 PM");

        dateUtilsMock.getCompanyDate = () => new Date("2023-05-29T00:04:26.000Z");
        expect(searchTest.lastModifiedString(new Date(), false)).toEqual("5/29/2023 12:04 AM");
    });
});