import {log} from "../mappings";
import {expect} from "@jest/globals";

const {
    loadSuiteScriptModule,
} = require('netsumo');

const configMock = {
    isDST: () => false,
    getValue: jest.fn(),
};
const informationMock = {
    getText: jest.fn(),
    getTimeZone: () => "(GMT-06:00) Central Time (US & Canada)",
}
informationMock.getText.mockReturnValue("(GMT-06:00) Central Time (US & Canada)");

const NSConfigMock = {
    Type: {
        COMPANY_INFORMATION: 'COMPANY_INFORMATION',
        COMPANY_PREFERENCES: 'COMPANY_PREFERENCES',
    },
    load: jest.fn(),
}


NSConfigMock.load.mockImplementation((data) => {
    if (data.type === "COMPANY_INFORMATION") {
        return informationMock;
    }
    if (data.type === "COMPANY_PREFERENCES") {
        return configMock;
    }
    throw "test mock error";
});

const Config = loadSuiteScriptModule('tmp/src/Utilities/Config.js');
const configCfg = {
    'N/log': log,
    'N/config': NSConfigMock,
};
const config = Config(configCfg);

describe('date converions', () => {
    test('12AM/PM', () => {
        const time = new Date('2023-05-29T18:04:26.000Z').getTime() / 1000;
        expect(new Date("2023-05-29T12:04:26.000Z")).toEqual(config.getCompanyDate(time, true));
    });

    test('Netsuite Fromats', () => {
        let dates = [
            "5-Mar-2018",
            "5.3.2018",
            "5-March-2018",
            "5 March, 2018",
            "2018/3/5",
            "2018-3-5",
            "05-Mar-2018",
            "05.03.2018",
            "05-March-2018",
            "05 March, 2018",
            "2018/03/05",
            "2018-03-05",
            "05-Mar-2018",
            "05-March-2018",
        ];

        let times = [
            '02:32:16',
            '2:32:16 AM',
            '02-32-16',
            '2:32:16 AM',
        ];
        compareDates(dates, times, "2018-03-05T08:32:16.000Z");

        times = [
            '14:32:16',
            '2:32:16 PM',
            '14-32-16',
            '2:32:16 PM',
        ];
        compareDates(dates, times, "2018-03-05T20:32:16.000Z");

        //12/12
        dates = [
            "5-Dec-2018",
            "5.12.2018",
            "5-December-2018",
            "5 December, 2018",
            "2018/12/5",
            "2018-12-5",
            "05-Dec-2018",
            "05.12.2018",
            "05-December-2018",
            "05 December, 2018",
            "2018/12/05",
            "2018-12-05",
            "05-Dec-2018",
            "05-December-2018",
        ];
        times = [
            '00:32:16',
            '12:32:16 AM',
            '00-32-16',
            '12:32:16 AM',
        ];
        compareDates(dates, times, "2018-12-05T06:32:16.000Z");

        //not supported
        dates = [
            "5/3/2018",
            "3/5/2018",
            "05/03/2018",
            "03/05/2018",
        ];
        compareDates(dates, times, null);

        //not supported
        dates = [
            "5/13/2018",
            "13/5/2018",
            "05/13/2018",
            "13/05/2018",
        ];
        times = [
            '12:32:16',
            '12:32:16 PM',
            '12-32-16',
            '12:32:16 PM',
        ];
        compareDates(dates, times, "2018-05-13T18:32:16.000Z");
    });
});

function compareDates(dates: string[], times: string[], compare: string | null): void
{
    for (let i in dates) {
        for (let j in times) {
            const dateString = dates[i] + " " + times[j];
            const result = config.dateFromNSString(dateString);
            if (compare) {
                expect(new Date(Date.parse(compare))).toEqual(result);
            } else {
                expect(null).toEqual(result);
            }
        }
    }
}