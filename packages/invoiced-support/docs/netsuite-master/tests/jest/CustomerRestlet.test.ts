/** globals Object */

import {log} from './mappings';

const {
    loadSuiteScriptModule,
} = require('netsumo');

const searchMock = {
};

const recordMock = {
}

const mockAddressList = {
    setValue: jest.fn(),
}

const modelMock = {
    customer: {
        getFields: jest.fn(),
        getLineCount: jest.fn(),
        selectNewLine: jest.fn(),
        selectLine: jest.fn(),
        getCurrentSublistSubrecord: () => mockAddressList,
        save: jest.fn(),
        commitLine: jest.fn(),
        toJSON: () => ({}),
    },
}


const saveCustomerFactoryMock = {
    create: () => modelMock,
}

const ValueSetterMock = {
    set: jest.fn(),
}

const CustomerRestletLib = loadSuiteScriptModule('tmp/src/CustomerRestlet.js');
const CustomerRestletProvider = CustomerRestletLib({
    'N/record': recordMock,
    'N/search': searchMock,
    'N/log': log,
    'tmp/src/Libs/SaveCustomerFactory': saveCustomerFactoryMock,
    'tmp/src/Libs/ValueSetter': ValueSetterMock,
});


const data = {
    parent_customer: {
        id: 1,
        netsuite_id: 2,
        companyname: 'Parent customer',
        accountnumber: 'PC01',
    },
    //invoiced id
    id: 3,
    netsuite_id: 4,
    name: 'Child customer',
    number: 'CC01',
    attention_to: 'attention_to',
    addr1: 'address1',
    addr2: 'address2',
    city: 'city',
    state: 'state',
    country: 'country',
    zip: 'postal_code',
    some_random_value: 'some_random',
};


function clearMock() {
    ValueSetterMock.set.mockClear();
    mockAddressList.setValue.mockClear();
    modelMock.customer.commitLine.mockClear();
    modelMock.customer.save.mockClear();
}

describe('On POST', () => {
    test('Test All' , () => {
        clearMock();
        const clone = Object.assign({}, data);

        modelMock.customer.getFields.mockReturnValueOnce(['id']);
        //no existing address
        modelMock.customer.getLineCount.mockReturnValueOnce(0);
        CustomerRestletProvider.post(clone);

        expect(modelMock.customer.selectNewLine).toHaveBeenCalledTimes(1);
        expect(modelMock.customer.commitLine).toHaveBeenCalledTimes(1);


        expect(ValueSetterMock.set).toHaveBeenCalledTimes(2);
        expect(ValueSetterMock.set).toHaveBeenNthCalledWith(1, modelMock.customer,
            clone,
            ['id'],
        );
        expect(ValueSetterMock.set).toHaveBeenNthCalledWith(2, mockAddressList, clone, []);
        expect(modelMock.customer.save).toHaveBeenCalledTimes(1);
    });

    test('Test All with custom field' , () => {
        clearMock();
        const clone = Object.assign({}, data);
        delete clone.address2;
        delete clone.attention_to;

        modelMock.customer.getFields.mockReturnValueOnce(['name', 'id']);
        //existing address
        modelMock.customer.getLineCount.mockReturnValueOnce(1);
        CustomerRestletProvider.post(clone);

        expect(modelMock.customer.selectLine).toHaveBeenCalledTimes(1);
        expect(modelMock.customer.commitLine).toHaveBeenCalledTimes(1);


        expect(ValueSetterMock.set).toHaveBeenCalledTimes(2);
        expect(ValueSetterMock.set).toHaveBeenNthCalledWith(1, modelMock.customer,
            clone,
            ['id'],
        );
        expect(ValueSetterMock.set).toHaveBeenNthCalledWith(2, mockAddressList, clone, []);
        expect(modelMock.customer.save).toHaveBeenCalledTimes(1);
    });

    test('Test All with multiply addresses' , () => {
        clearMock();
        const clone = Object.assign({}, data);

        modelMock.customer.getFields.mockReturnValueOnce(['name', 'number']);
        //existing address
        modelMock.customer.getLineCount.mockReturnValueOnce(2);
        CustomerRestletProvider.post(clone);

        expect(ValueSetterMock.set).toHaveBeenCalledTimes(1);
        expect(ValueSetterMock.set).toHaveBeenNthCalledWith(1, modelMock.customer,
            clone,
            ['id'],
            );
        expect(modelMock.customer.save).toHaveBeenCalledTimes(1);
    });

    test('Test All without address input' , () => {
        clearMock();
        const clone = Object.assign({}, data);
        delete clone.address1;
        delete clone.address2;
        delete clone.attention_to;
        delete clone.city;
        delete clone.state;
        delete clone.country;
        delete clone.postal_code;



        modelMock.customer.getFields.mockReturnValueOnce([]);
        //existing address
        CustomerRestletProvider.post(clone);

        expect(mockAddressList.setValue).toHaveBeenCalledTimes(0);
        expect(modelMock.customer.save).toHaveBeenCalledTimes(1);
    });
});
