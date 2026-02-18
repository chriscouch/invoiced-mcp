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

let resultSetMock = {
    getRange: jest.fn(),
    each: jest.fn(),
};

advancedJobsHelper.getCustomerId.mockReturnValue(123456);

const recordMock = {
    load: jest.fn(),
    Type: types,
    transform: jest.fn(),
    create: jest.fn(),
    delete: jest.fn(),
}
const convenienceFeeMock = {
    findExistingConvenienceFee: jest.fn(),
    createConvenienceFeeInvoice: jest.fn(),
};

const ConvenienceFeeFactoryMock = {
    getInstance: jest.fn(),
}

const cfgMock = {
    getStartDate: jest.fn(),
    doSyncPaymentRead: jest.fn(),
}
cfgMock.doSyncPaymentRead.mockReturnValue(true);

const CustomerMatcherMock = jest.fn();
CustomerMatcherMock.mockReturnValue(1);
const CustomerMatcherMockProvider = {
    getCustomerIdDecorated: CustomerMatcherMock,

}

const SavePaymentFactoryLib = loadSuiteScriptModule('tmp/src/Libs/SavePaymentFactory.js');
const SavePaymentFactoryLibProvider = SavePaymentFactoryLib({
    'N/record': recordMock,
    'N/log': log,
    'tmp/src/Models/ConvenienceFee': ConvenienceFeeFactoryMock,
    'tmp/src/Libs/CustomerMatcher': CustomerMatcherMockProvider,
});


describe('Test save Factory', () => {
    describe('test match all', () => {
        const data = {
            "date": 1667941389,
            "amount": 1,
            "netsuite_id": null,
            "custbody_invoiced_id": 19394,
            "customer": null,
            "parent_customer": {
                "companyname": '1687',
                "accountnumber": 'CUST-00002',
            },
            "customer_name": "1687",
            "customer_number": "CUST-00002",
            "applied": [{
                "id": 42628,
                "type": "credit_note",
                "amount": 95.95,
                "credit_note": 7228,
                "document_type": "invoice",
                "invoice": 190820,
                "invoice_netsuite_id": "100081",
                "invoice_number": "INK04000360",
                "credit_note_netsuite_id": null,
                "credit_note_number": "MEM00000432"
            }, {
                "id": 42629,
                "type": "invoice",
                "amount": 1,
                "invoice": 190820,
                "invoice_netsuite_id": "100081",
                "invoice_number": "INK04000360"
            }],
            "currency": "usd",
            "voided": false,
            "date_voided": null,
            "method": "credit_card",
            "charge": {
                "checknum": "636ac40d505ff",
                "gateway": "test",
                "payment_source": "Visa *4242 (expires Feb '33)",
                "type": "card"
            }
        };

        test('create save payment path', () => {
            const paymentMock = {
                toString: () => 'dynamic record',
                getLineCount: () => 1,
                setValue: jest.fn(),
                getValue: jest.fn(),
                save: jest.fn(),
                getSublistValue: jest.fn(),
                setSublistValue: jest.fn(),
                getCurrentSublistValue: jest.fn(),
                setCurrentSublistValue: jest.fn(),
                selectLine: jest.fn(),
                commitLine: jest.fn(),
            }

            const data2 = JSON.parse(JSON.stringify(data));
            data2.netsuite_id = 1;
            try {
                SavePaymentFactoryLibProvider.create(cfgMock, new Date(), data2);
                expect(recordMock.load).toHaveBeenCalledTimes(1);
                throw "no error thrown";
            } catch (e) {
                expect(e).toEqual("Customer not found");
            }

            ConvenienceFeeFactoryMock.getInstance.mockReset();
            convenienceFeeMock.createConvenienceFeeInvoice.mockReset();
            convenienceFeeMock.findExistingConvenienceFee.mockReset();
            recordMock.load.mockClear();
            recordMock.load.mockReturnValue(paymentMock);
            const createPayment= SavePaymentFactoryLibProvider.create(cfgMock, new Date(), data2);
            expect(recordMock.load).toHaveBeenCalledTimes(1);

            //apply convenience fee test
            let invoice = createPayment.applyConvenienceFee(data2, []);
            expect(invoice).toBe(null);
            expect(convenienceFeeMock.findExistingConvenienceFee).toHaveBeenCalledTimes(0);
            expect(convenienceFeeMock.createConvenienceFeeInvoice).toHaveBeenCalledTimes(0);

            ConvenienceFeeFactoryMock.getInstance.mockClear();
            ConvenienceFeeFactoryMock.getInstance.mockReturnValue(convenienceFeeMock);
            invoice = createPayment.applyConvenienceFee(data2, []);
            expect(invoice).toBe(null);
            expect(convenienceFeeMock.findExistingConvenienceFee).toHaveBeenCalledTimes(1);

            convenienceFeeMock.createConvenienceFeeInvoice.mockClear();
            convenienceFeeMock.findExistingConvenienceFee.mockClear();
            convenienceFeeMock.createConvenienceFeeInvoice.mockReturnValue(1);
            invoice = createPayment.applyConvenienceFee(data2, []);
            expect(invoice).toBe(null);
            expect(convenienceFeeMock.findExistingConvenienceFee).toHaveBeenCalledTimes(1);

            convenienceFeeMock.createConvenienceFeeInvoice.mockClear();
            convenienceFeeMock.findExistingConvenienceFee.mockClear();
            convenienceFeeMock.findExistingConvenienceFee.mockReturnValue(1);
            invoice = createPayment.applyConvenienceFee(data2, []);
            expect(invoice).toBe(1);
            expect(convenienceFeeMock.findExistingConvenienceFee).toHaveBeenCalledTimes(1);
            expect(convenienceFeeMock.createConvenienceFeeInvoice).toHaveBeenCalledTimes(0);

        });
    });
});