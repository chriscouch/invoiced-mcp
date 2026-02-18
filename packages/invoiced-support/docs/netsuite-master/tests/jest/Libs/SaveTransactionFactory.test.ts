/** globals Object */

import {log} from '../mappings';

const {
    loadSuiteScriptModule,
} = require('netsumo');

const searchResultMock = jest.fn();
searchResultMock.mockReturnValue([]);

const searchMock = {
    Operator: {
        IS: 'is',
    },
    create: () => ({
        run: () => ({
            getRange: searchResultMock
        }),
    }),
    Type: {
        INVOICE: 'invoice',
    },
};

const transactionMock = {
    void: jest.fn(),
    Type: {
        INVOICE: 'invoice',
    },
}

const modelMock = {
    getFields: jest.fn(),
    setValue: jest.fn(),
    selectNewLine: jest.fn(),
    selectLine: jest.fn(),
    save: jest.fn(),
    commitLine: jest.fn(),
    toJSON: () => ({}),
    id: null,
}

const recordMock = {
    Type: {
        CUSTOMER: 'customer',
        INVOICE: 'invoice',
        CREDIT_MEMO: 'credit_memo',
    },
    transform: jest.fn(),
    create: jest.fn(),
}
recordMock.transform.mockReturnValue(modelMock);


const customerMatcherMock = {
    getCustomerIdDecorated: jest.fn(),
}

const ValueSetterMock = {
    // set: (a, b, c) => {
    //     console.log(a);
    //     console.log(b);
    //     console.log(c)
    // },
    set: jest.fn(),
    setSublist: jest.fn(),
}

const facadeMock = {
    patch: jest.fn(),
}


const SubsidiaryKeyMock = {
    get: () => 'test',
}

const SaveTransactionFactoryLib = loadSuiteScriptModule('tmp/src/Libs/SaveTransactionFactory.js');
const SaveTransactionFactoryProvider = SaveTransactionFactoryLib({
    'N/record': recordMock,
    'N/search': searchMock,
    'N/log': log,
    'N/transaction': transactionMock,
    'tmp/src/Libs/CustomerMatcher': customerMatcherMock,
    'tmp/src/Libs/ValueSetter': ValueSetterMock,
    'tmp/src/Utilities/SubsidiaryKey': SubsidiaryKeyMock,
});

const item1 = {
    name: 'name',
    description: 'description',
    rate: 2,
    quantity: 3,
};

const item2 = {
    name: 'name2',
    description: 'description2',
    rate: 4,
    quantity: 5,
};

const data = {
    parent_customer: {
        id: 1,
        netsuite_id: 2,
        item: 1,
    },
    //invoiced id
    id: 3,
    memo: 'memo',
    status: 'open',
    items: [item1],
    currencysymbol: 'usd',
    tranid: 'INVD-0001',
    trandate: 1234567,
    duedate: 7654321,
    voided: false
};


function clearMock() {
    ValueSetterMock.set.mockClear();
    modelMock.save.mockClear();
    modelMock.id = null;
    ValueSetterMock.setSublist.mockClear();
    customerMatcherMock.getCustomerIdDecorated.mockClear();
    facadeMock.patch.mockClear();
    searchResultMock.mockClear();
    recordMock.create.mockClear();
    recordMock.transform.mockClear();
}

describe('On POST', () => {
    test('Test Already mapped' , () => {
        clearMock();
        const clone = Object.assign({}, data);
        clone.netsuite_id = 4;

        const result = SaveTransactionFactoryProvider.create(clone, facadeMock, 'invoice');
        expect(result).toEqual({message: "Existing Transaction", status: 406});
        expect(modelMock.save).toHaveBeenCalledTimes(0);
        expect(transactionMock.void).toHaveBeenCalledTimes(0);

        clone.voided = true;
        const result2 = SaveTransactionFactoryProvider.create(clone, facadeMock, 'invoice');
        expect(result2).toEqual({message: "Transaction voided", status: 406});
        expect(modelMock.save).toHaveBeenCalledTimes(0);
        expect(transactionMock.void).toHaveBeenCalledTimes(1);
    });


    test('Test found' , () => {
        clearMock();
        const clone = Object.assign({}, data);
        searchResultMock.mockReturnValueOnce([{
            id: 1,
            getValue: () => 4,
        }]);
        facadeMock.patch.mockReturnValueOnce(1);
        const result = SaveTransactionFactoryProvider.create(clone, facadeMock, 'invoice');
        expect(result).toEqual({message: "Existing Transaction", status: 406});
    });

    test('Test No Parent' , () => {
        clearMock();
        const clone = Object.assign({}, data);

        // modelMock.getFields.mockReturnValueOnce(['id']);
        const result = SaveTransactionFactoryProvider.create(clone, facadeMock, 'invoice');
        expect(result).toEqual({message: "No parent customer found", status: 406});
    });

    test('Test One Line Item' , () => {
        clearMock();
        const clone = Object.assign({}, data);
        clone.discountrate = 1;
        clone.taxrate = 2;

        customerMatcherMock.getCustomerIdDecorated.mockReturnValueOnce(1);
        modelMock.getFields.mockReturnValueOnce(['id']);
        const result = SaveTransactionFactoryProvider.create(clone, facadeMock, 'invoice');
        expect(result).toBe(modelMock);

        expect(ValueSetterMock.set).toHaveBeenCalledTimes(1);
        expect(ValueSetterMock.set).toHaveBeenNthCalledWith(1, modelMock,
            clone,
            ['id'],
        );
        expect(ValueSetterMock.setSublist).toHaveBeenCalledTimes(1);
        expect(ValueSetterMock.setSublist).toHaveBeenNthCalledWith(1, modelMock, [item1], 'item', []);
        expect(modelMock.save).toHaveBeenCalledTimes(1);
    });

    test('Test Invoice multiply line items' , () => {
        clearMock();
        const clone = Object.assign({}, data);
        delete clone.description;
        clone.items = [item1, item2];
        clone.discountrate = 1;
        clone.discountitem = 100;
        clone.taxrate = 2;
        clone.taxlineitem = 200;

        customerMatcherMock.getCustomerIdDecorated.mockReturnValueOnce(1);
        modelMock.getFields.mockReturnValueOnce(['name', 'id']);
        const result = SaveTransactionFactoryProvider.create(clone, facadeMock, 'invoice');
        expect(result).toBe(modelMock);

        expect(recordMock.transform).toHaveBeenCalledTimes(1);
        expect(ValueSetterMock.set).toHaveBeenCalledTimes(1);
        expect(ValueSetterMock.set).toHaveBeenNthCalledWith(1, modelMock,
            clone,
            ['id'],
        );
        expect(ValueSetterMock.setSublist).toHaveBeenCalledTimes(1);
        expect(ValueSetterMock.setSublist).toHaveBeenNthCalledWith(1, modelMock, [item1, item2, { item: 200, rate: 2, quantity: 1, description: "Invoiced Calculated Tax", }], 'item', []);
        expect(modelMock.save).toHaveBeenCalledTimes(1);
    });

    test('Test Credit Memo multiply line items' , () => {
        clearMock();
        const clone = Object.assign({}, data);
        delete clone.description;
        clone.items = [item1, item2];
        clone.discountrate = 1;
        clone.discountitem = 100;
        clone.taxrate = 2;
        clone.taxlineitem = 200;

        customerMatcherMock.getCustomerIdDecorated.mockReturnValueOnce(1);
        modelMock.getFields.mockReturnValueOnce(['name', 'id']);
        recordMock.create.mockReturnValueOnce(modelMock);
        const result = SaveTransactionFactoryProvider.create(clone, facadeMock, 'credit_memo');
        expect(result).toBe(modelMock);

        expect(ValueSetterMock.set).toHaveBeenCalledTimes(1);
        expect(recordMock.create).toHaveBeenCalledTimes(1);
        expect(ValueSetterMock.set).toHaveBeenNthCalledWith(1, modelMock,
            clone,
            ['id'],
        );
        expect(ValueSetterMock.setSublist).toHaveBeenCalledTimes(1);
        expect(ValueSetterMock.setSublist).toHaveBeenNthCalledWith(1, modelMock, [item1, item2, { item: 200, rate: 2, quantity: 1, description: "Invoiced Calculated Tax", }], 'item', []);
        expect(modelMock.save).toHaveBeenCalledTimes(1);
    });
});
