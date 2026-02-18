import {list, log} from './mappings';
import {expect} from "@jest/globals";
import {PaymentsScriptletInputParameters} from "../../src/PaymentsModelScriptlet";

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

const logMockDebug = jest.spyOn(log, 'debug');

const transactionMock = {
    Type: types,
    void: jest.fn(),
};

const invoiceFacadeMockPatch = jest.fn();
const invoiceFacadeMock = {
    patch: invoiceFacadeMockPatch,
}
const creditNoteFacadeMockPatch = jest.fn();
const creditNoteFacadeMock = {
    patch: creditNoteFacadeMockPatch,
}

const paymentDepositMock = {
    fetch: jest.fn(),
}

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

const configMock = {
    getInstance: () => cfgMock
}
const dateUtilitiesMock = {
    getCompanyDate: jest.fn(),
}

const savePaymentMock = {
    payment: null,
    customer: null,
    applyConvenienceFee: jest.fn(),
}

const savePaymentFactoryMock = {
    create: function() {
        throw "test";
    },
}
const subsidiaryKeyMock = {
    get: ()=> {
    },
}

const PaymentsModelScriptletLib = loadSuiteScriptModule('tmp/src/PaymentsModelScriptlet.js');
const PaymentsModelScriptletProvider = PaymentsModelScriptletLib({
    'N/log': log,
    'N/search': searchMock,
    'N/record': recordMock,
    'N/transaction': transactionMock,
    'tmp/src/Models/List': list,
    'tmp/src/PaymentDeposit': paymentDepositMock,
    'tmp/src/Facades/InvoiceFacade': invoiceFacadeMock,
    'tmp/src/Facades/CreditNoteFacade': creditNoteFacadeMock,
    'tmp/src/Utilities/Config': configMock,
    'tmp/src/Utilities/DateUtilities': dateUtilitiesMock,
    'tmp/src/Global': {
        INVOICED_RESTRICT_SYNC_ENTITY_FIELD: false,
    },
    'tmp/src/Libs/SavePaymentFactory': savePaymentFactoryMock,
    'tmp/src/Utilities/SubsidiaryKey': subsidiaryKeyMock,
});


describe('On POST void test', () => {
    describe('test no sync', () => {
        test('test no sync', () => {
            cfgMock.doSyncPaymentRead.mockReturnValueOnce(false);
            PaymentsModelScriptletProvider.post({});
            expect(logMockDebug).toHaveBeenLastCalledWith('Read sync disabled', undefined);
        });
    });

    describe('test void', () => {
        test('Test Void No Payment', () => {
            resultSetMock.getRange.mockReturnValueOnce([]);
            PaymentsModelScriptletProvider.post({
                voided: 1,
            });
            expect(logMockDebug).toHaveBeenLastCalledWith('No payment id specified for void operation', undefined);
        });
        test('Test Void No Payment Id', () => {
            resultSetMock.getRange.mockReturnValueOnce([{
                foo: 'bar',
            }]);
            PaymentsModelScriptletProvider.post({
                voided: 1,
                netsuite_id: 1,
            });
            expect(transactionMock.void).toHaveBeenCalledTimes(1);
        });
    });


    describe('test apply', () => {
        const data: PaymentsScriptletInputParameters = {
            applied: [
                {
                    amount: 1.11,
                    type: 'invoice',
                    invoice: 2,
                    invoice_netsuite_id: 1,
                },
                {
                    amount: 2.12,
                    type: 'convenience_fee',
                    invoice_netsuite_id: null,
                },
                {
                    amount: 3.13,
                    type: 'credit_note',
                    credit_note: 3,
                    invoice_netsuite_id: null,
                    credit_note_number: 'CNT-503',
                    credit_note_netsuite_id: 503,
                },
            ],
            customer:  {
                id: 5,
                number: 'CUS-0001',
                name: 'test test',
                netsuite_id: 1,
            },
            id: 6,
            netsuite_id: 1,
            voided: 0,
            date: 1111111,
            currency: 'usd',
            method: 'other',
            charge: null,
            amount: 12.52,
            reference: null,
        };

        dateUtilitiesMock.getCompanyDate.mockReturnValue(1);
        test('Test Sync Threshold', () => {
            savePaymentMock.applyConvenienceFee.mockReturnValueOnce(null);
            cfgMock.getStartDate.mockReturnValueOnce(2);
            expect(PaymentsModelScriptletProvider.post(data)).toBe(undefined);
        });
        cfgMock.getStartDate.mockReturnValue(0);

        describe('Test general', () => {
            data.netsuite_id = null;
            if (data.applied[0]) {
                data.applied[0]['invoice_number'] = 'INVD-502';
            }
            //INVD-3138 we removed custbody_invoiced_id
            //so minus one call of payment model set value everywhere
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
                id: 0,
            }
            paymentMock.getValue.mockReturnValue({});
            const creditNoteMock = {
                toString: () => 'dynamic record',
                getLineCount: () => 1,
                setValue: jest.fn(),
                getValue: jest.fn(),
                save: jest.fn(),
                getSublistValue: () => jest.fn(),
                setSublistValue: jest.fn(),
                getCurrentSublistValue: jest.fn(),
                setCurrentSublistValue: jest.fn(),
                selectLine: jest.fn(),
                commitLine: jest.fn(),
            }
            recordMock.load.mockImplementation((data) => {
                if (data.type === "CUSTOMER_PAYMENT") {
                    return paymentMock;
                }
                if (data.type === "CREDIT_MEMO") {
                    return creditNoteMock;
                }
                throw "test mock error";
            });
            //no convenience fee
            savePaymentMock.applyConvenienceFee.mockReturnValue(null);

            //invoice search
            //with payment
            resultSetMock.getRange.mockReturnValue([{
                id: 321,
            }]);
            paymentDepositMock.fetch.mockReturnValue(null);

            const clearMocks = () => {
                paymentMock.setValue.mockClear();
                paymentMock.save.mockClear();
                savePaymentMock.applyConvenienceFee.mockClear();
                savePaymentMock.payment = paymentMock as never;
                savePaymentFactoryMock.create = () => savePaymentMock as never;
            }

            describe('with invoiced id', () => {
                test('with account undeposited ' +
                    'with charge', () => {
                    clearMocks();
                    data.netsuite_id = 1;
                    paymentMock.getCurrentSublistValue.mockReturnValue(1);
                    const clone = Object.assign({}, data);
                    clone.charge = {
                        gateway: 'test1',
                        payment_source: 'test2',
                        checknum: 'test3',
                    };
                    paymentDepositMock.fetch.mockReturnValueOnce({
                        custrecord_invd_undeposited_funds: true,
                    });
                    PaymentsModelScriptletProvider.post(clone);
                    expect(paymentMock.setValue).toHaveBeenCalledTimes(10);
                    expect(paymentMock.setValue).toHaveBeenCalledWith({
                        fieldId: 'undepfunds',
                        value: 'T',
                        ignoreFieldChange: true,
                    });
                });
                test('with payments ' +
                    'with account ' +
                    'with charge', () => {
                    clearMocks();
                    data.netsuite_id = null;
                    //with payments
                    //.findPayment
                    resultSetMock.getRange.mockReturnValueOnce([]);
                    //.findOrCreatePayment

                    //no account
                    paymentDepositMock.fetch.mockReturnValueOnce({
                        custrecord_ivnd_deposit_bank_account: 'acct',
                    });
                    PaymentsModelScriptletProvider.post(data);
                    expect(paymentMock.setValue).toHaveBeenCalledTimes(10);
                    expect(paymentMock.setValue).toHaveBeenCalledWith({
                        fieldId: 'account',
                        value: 'acct',
                        ignoreFieldChange: true,
                    });

                });
                test(
                    'no account ' +
                    'no charge', () => {
                        clearMocks();
                        data.netsuite_id = 1;
                        PaymentsModelScriptletProvider.post(data);
                        expect(paymentMock.setValue).toHaveBeenCalledTimes(8);
                        expect(paymentMock.save).toHaveBeenCalledTimes(1);
                    });
            });

            test('convenience fee', () => {
                    clearMocks();
                    //convenience fee .applyConvenienceFee
                    savePaymentMock.applyConvenienceFee.mockReturnValue({
                        id: 1,
                        amount: 1.1,
                        new: true,
                    });

                    PaymentsModelScriptletProvider.post(data);
                    expect(paymentMock.setValue).toHaveBeenCalledTimes(8);
                    expect(paymentMock.save).toHaveBeenCalledTimes(1);
                    expect(savePaymentMock.applyConvenienceFee).toHaveBeenCalledTimes(1);
                    expect(logMockDebug).toHaveBeenCalledWith('Invoices', {1: {
                        'invoicedId': null,
                        'amount': 1.1,
                    }});
            });


            describe('without invoiced id', () => {
                const data2 = JSON.parse(JSON.stringify(data));
                data2.applied[0].invoice_netsuite_id = null;

                test('Invoice found', () => {
                    resultSetMock.each.mockImplementationOnce((data) => {
                        data({
                            id: 1,
                            getValue: () => 'INVD-502',
                        });
                    });

                    paymentMock.getCurrentSublistValue.mockReturnValueOnce(1);
                    PaymentsModelScriptletProvider.post(data2);
                    
                });

                test('Customer found in metadata Already mapped', () => {




                    //invoice
                    resultSetMock.each.mockImplementationOnce((data) => {
                        const result = jest.fn();
                        data(result);
                    });
                    //payment
                    resultSetMock.getRange.mockReturnValueOnce([{
                        id: 3,
                    }]);

                    PaymentsModelScriptletProvider.post(data2);

                });

                test('with applied CN', () => {
                    creditNoteMock.getCurrentSublistValue.mockReturnValueOnce(321);
                    PaymentsModelScriptletProvider.post(data2);
                    expect(creditNoteMock.save).toHaveBeenCalledTimes(0);
                    
                });

                describe('rollback', () => {
                    const convFeeObj = {
                        id: 1,
                        amount: 1.1,
                        new: false,
                        oldAmount: 10,
                        invoice: {
                            id: 1,
                            setValue: jest.fn(),
                            save: jest.fn(),
                        },
                    };

                    test('CN failed', () => {

                        recordMock.delete.mockClear();
                        convFeeObj.invoice.setValue.mockClear();
                        convFeeObj.invoice.save.mockClear();
                        paymentMock.getCurrentSublistValue.mockReturnValueOnce(1);
                        creditNoteMock.getCurrentSublistValue.mockReturnValueOnce(321);
                        savePaymentMock.applyConvenienceFee.mockReturnValueOnce(null);
                        const data3 = JSON.parse(JSON.stringify(data));
                        data3.applied[2].credit_note_netsuite_id = 123;
                        data3.applied[2].invoice_netsuite_id = 1;
                        data3.applied[2].invoice = 1;
                        recordMock.load.mockImplementation((data: any) => {
                            if (data.id === 123) {
                                throw "test error1";
                            }
                            return paymentMock;
                        })
                        try {
                            PaymentsModelScriptletProvider.post(data3);
                        } catch (e) {
                            expect(e).toEqual("test error1");
                        }
                        expect(recordMock.delete).toHaveBeenCalledTimes(0);
                        expect(convFeeObj.invoice.setValue).toHaveBeenCalledTimes(0);
                        expect(convFeeObj.invoice.save).toHaveBeenCalledTimes(0);
                        
                    });

                    test('CN failed with payment id', () => {

                        recordMock.delete.mockClear();
                        convFeeObj.invoice.setValue.mockClear();
                        convFeeObj.invoice.save.mockClear();
                        paymentMock.getCurrentSublistValue.mockReturnValueOnce(1);
                        paymentMock.id = 1;
                        creditNoteMock.getCurrentSublistValue.mockReturnValueOnce(321);
                        savePaymentMock.applyConvenienceFee.mockReturnValueOnce(null);
                        const data3 = JSON.parse(JSON.stringify(data));
                        data3.applied[2].credit_note_netsuite_id = 123;
                        data3.applied[2].invoice_netsuite_id = 1;
                        data3.applied[2].invoice = 1;
                        recordMock.load.mockImplementation((data: any) => {
                            if (data.id === 123) {
                                throw "test error1";
                            }
                            return paymentMock;
                        })
                        try {
                            PaymentsModelScriptletProvider.post(data3);
                        } catch (e) {
                            expect(e).toEqual("test error1");
                        }
                        expect(recordMock.delete).toHaveBeenCalledTimes(1);
                        expect(convFeeObj.invoice.setValue).toHaveBeenCalledTimes(0);
                        expect(convFeeObj.invoice.save).toHaveBeenCalledTimes(0);

                    });
                    test('payment failed with new fee', () => {

                        recordMock.delete.mockClear();
                        convFeeObj.invoice.setValue.mockClear();
                        convFeeObj.invoice.save.mockClear();
                        convFeeObj.new = true;
                        savePaymentMock.applyConvenienceFee.mockReturnValue(convFeeObj);
                        paymentMock.getCurrentSublistValue.mockImplementation(() => {
                            throw "test error2";
                        });
                        try {
                            PaymentsModelScriptletProvider.post(data);
                        } catch (e) {
                            expect(e).toEqual("test error2");
                        }
                        expect(convFeeObj.invoice.setValue).toHaveBeenCalledTimes(0);
                        expect(convFeeObj.invoice.save).toHaveBeenCalledTimes(0);
                        
                    });
                    test('payment failed with old fee', () => {
                        recordMock.delete.mockClear();
                        convFeeObj.invoice.setValue.mockClear();
                        convFeeObj.invoice.save.mockClear();
                        convFeeObj.new = false;
                        savePaymentMock.applyConvenienceFee.mockReturnValue(convFeeObj);
                        paymentMock.getCurrentSublistValue.mockImplementation(() => {
                            throw "test error3";
                        });
                        try {
                            PaymentsModelScriptletProvider.post(data);
                        } catch (e) {
                            expect(e).toEqual("test error3");
                        }
                        expect(recordMock.delete).toHaveBeenCalledTimes(0);
                        expect(convFeeObj.invoice.setValue).toHaveBeenCalledTimes(1);
                        expect(convFeeObj.invoice.save).toHaveBeenCalledTimes(1);
                        
                    });
                    test('payment failed without fee', () => {
                        recordMock.delete.mockClear();
                        convFeeObj.invoice.setValue.mockClear();
                        convFeeObj.invoice.save.mockClear();
                        savePaymentMock.applyConvenienceFee.mockReturnValue(convFeeObj);
                        savePaymentMock.applyConvenienceFee.mockReturnValueOnce(null);
                        paymentMock.getCurrentSublistValue.mockImplementation(() => {
                            throw "test error4";
                        });
                        try {
                            PaymentsModelScriptletProvider.post(data);
                        } catch (e) {
                            expect(e).toEqual("test error4");
                        }
                        expect(recordMock.delete).toHaveBeenCalledTimes(0);
                        expect(convFeeObj.invoice.setValue).toHaveBeenCalledTimes(0);
                        expect(convFeeObj.invoice.save).toHaveBeenCalledTimes(0);
                    });
                });
            });
        });
    });


});