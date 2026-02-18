import {advancedJobsHelper, log} from '../mappings';
import {expect} from "@jest/globals";

const {
    loadSuiteScriptModule,
} = require('netsumo');

const types = {
    CUSTOMER_PAYMENT: 'CUSTOMER_PAYMENT',
    CUSTOMER: 'CUSTOMER',
    CREDIT_MEMO: 'CREDIT_MEMO',
    INVOICE: 'INVOICE',
};

const searchMock = {
    create: () => createdSearchMock,
    createFilter: jest.fn(),
    Type: types,
    Operator: {
        EQUALTO: 1,
        IS: 1,
    }
};
const createdSearchMock = {
    run: () => resultSetMock,
}

let resultSetMock = {
    getRange: jest.fn(),
    each: jest.fn(),
};

advancedJobsHelper.getCustomerId.mockReturnValue(123456);

const logMockDebug = jest.spyOn(log, 'debug');

const recordMock = {
    load: jest.fn(),
    Type: types,
    transform: jest.fn(),
    create: jest.fn(),
    delete: jest.fn(),
}

const cfgMock = {
    getStartDate: jest.fn(),
    doSyncPaymentRead: jest.fn(),
}
cfgMock.doSyncPaymentRead.mockReturnValue(true);

const customerFacadeMockPatch = jest.fn();
const customerFacadeMock = {
    patch: customerFacadeMockPatch,
}

const SubsidiaryKeyMock = {
    get: () => 'test',
}

const CustomerMatcherLib = loadSuiteScriptModule('tmp/src/Libs/CustomerMatcher.js');
const CustomerMatcherLibProvider = CustomerMatcherLib({
    'N/record': recordMock,
    'N/search': searchMock,
    'N/log': log,
    'tmp/src/Facades/CustomerFacade': customerFacadeMock,
    'tmp/src/Utilities/AdvancedJobsHelper': advancedJobsHelper,
    'tmp/src/Utilities/SubsidiaryKey': SubsidiaryKeyMock,
});


describe('Test save Factory', () => {
    describe('test match all', () => {
        test('customer filter', () => {
            let data = ["CUST-00002", "1687", null];
            logMockDebug.mockClear();
            resultSetMock.getRange.mockReturnValue([]);
            expect(CustomerMatcherLibProvider.getCustomerIdDecorated(...data)).toBe(null);
            expect(logMockDebug).toHaveBeenCalledWith('Customer Filter',[
                [ 'accountnumber', 1, 'CUST-00002' ],
                'or',
                [ 'entityid', 1, 'CUST-00002' ]
            ]);
            expect(logMockDebug).toHaveBeenCalledWith('Customer Filter',[  [ 'companyname', 1, '1687' ], 'or',   [ 'lastname', 1, '1687' ] ]);
            expect(logMockDebug).toHaveBeenCalledWith('Customer Filter',[ [ 'entityid', 1, '1687' ], 'or', [ 'altname', 1, '1687' ]  ]);
            expect(resultSetMock.getRange).toHaveBeenCalledTimes(3);


            logMockDebug.mockClear();
            resultSetMock.getRange.mockReset();
            resultSetMock.getRange.mockReturnValue([{id: 1}, {id: 2}]);
            expect(CustomerMatcherLibProvider.getCustomerIdDecorated(...data)).toBe(null);
            expect(resultSetMock.getRange).toHaveBeenCalledTimes(3);
            expect(customerFacadeMockPatch).toHaveBeenCalledTimes(0);

            logMockDebug.mockClear();
            customerFacadeMockPatch.mockClear();
            resultSetMock.getRange.mockReset();
            resultSetMock.getRange.mockReturnValueOnce([{id: 123456, getValue: () => 654321 }]);
            expect(CustomerMatcherLibProvider.getCustomerIdDecorated(...data)).toBe(123456);
            expect(resultSetMock.getRange).toHaveBeenCalledTimes(1);
            expect(customerFacadeMockPatch).toHaveBeenCalledTimes(1);


            data = ["", "test test", null];
            customerFacadeMockPatch.mockClear();
            logMockDebug.mockClear();
            resultSetMock.getRange.mockReset();
            resultSetMock.getRange.mockReturnValue([]);
            expect(CustomerMatcherLibProvider.getCustomerIdDecorated(...data)).toBe(null);
            expect(resultSetMock.getRange).toHaveBeenCalledTimes(2);
            expect(logMockDebug).toHaveBeenCalledWith('Customer Filter',[
                [ 'companyname', 1, 'test test' ],
                'or',
                [ [ 'firstname', 1, 'test' ], 'or', [ 'lastname', 1, 'test' ] ]
            ]);
            expect(logMockDebug).toHaveBeenCalledWith('Customer Filter', [ [ 'entityid', 1, 'test test' ], 'or', [ 'altname', 1, 'test test' ] ]);
            expect(customerFacadeMockPatch).toHaveBeenCalledTimes(0);


            data = ["", "", null];
            resultSetMock.getRange.mockClear();
            expect(CustomerMatcherLibProvider.getCustomerIdDecorated(...data)).toBe(null);
            expect(resultSetMock.getRange).toHaveBeenCalledTimes(0);
        });
    });
});