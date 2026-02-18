import objectFactory, {context, list} from '../mappings';
import {ConvenienceFeeFactoryInterface} from "../../../src/Models/ConvenienceFee";
import {InvoicedPaymentApplication} from "../../../src/PaymentsModelScriptlet";

beforeEach(() => {
    jest.clearAllMocks();
})

const invoiceMock = {
    setValue: jest.fn(),
    selectNewLine: jest.fn(),
    setCurrentSublistValue: jest.fn(),
    commitLine: jest.fn(),
    save: jest.fn(),
    getValue: jest.fn(),
}

const paymentMock = {
    setValue: jest.fn(),
    selectNewLine: jest.fn(),
    setCurrentSublistValue: jest.fn(),
    commitLine: jest.fn(),
    save: jest.fn(),
    getValue: jest.fn(),
}

const listMock = {
    map: () => jest.fn(),
}

const listFactoryMock = {
    context: {
        getLineCount: jest.fn(),
    },
    getInstance: () => listMock,
}

const recordMock = {
    load: jest.fn(),
    create: () => invoiceMock,
    Type: {
        CUSTOMER: "CUSTOMER",
        INVOICE: "INVOICE",
    }
}
const listSpy = jest.spyOn(list, 'getInstance');;

const convenienceFeeMock =jest.fn();

const configMock = {
    getConvenienceFee: () => convenienceFeeMock,
    getConvenienceFeeTaxCode: jest.fn(),
}
const configMockLoadSpy = jest.spyOn(configMock, 'getConvenienceFee');
const configFactoryMock = {
    getInstance: () => configMock,
}

describe('Convenience fee', () => {
    const convenienceFeeFactory: ConvenienceFeeFactoryInterface = objectFactory('ConvenienceFee', {
        'tmp/src/Utilities/Config': configFactoryMock,
        'N/record': recordMock,
        'tmp/src/Models/List': listFactoryMock,
        'tmp/src/Global': {
            INVOICED_RESTRICT_SYNC_TRANSACTION_BODY_FIELD: false,
        },
    });

    test('no application', () => {
        
        expect(convenienceFeeFactory.getInstance([])).toBe(null);
        expect(configMockLoadSpy).toHaveBeenCalledTimes(0);
    });
    test('no set up', () => {
        
        expect(convenienceFeeFactory.getInstance([{
            amount: 1.11,
            type: 'invoice',
            invoice: 2,
            invoice_netsuite_id: 1,
        }])).toBe(null);
        expect(configMock.getConvenienceFee).toHaveBeenCalledTimes(1);
    });
    test('no fee line item', () => {
        
        expect(convenienceFeeFactory.getInstance([{
            amount: 1.11,
            type: 'invoice',
            invoice: 2,
            invoice_netsuite_id: 1,
        }])).toBe(null);
        expect(configMock.getConvenienceFee).toHaveBeenCalledTimes(1);
    });

    const data: InvoicedPaymentApplication[] = [
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
        }
    ];

    describe('createConvenienceFeeInvoice', () => {
        test('with tax code', () => {
            const convenienceFee = convenienceFeeFactory.getInstance(data);
            if (convenienceFee === null) {
                throw "convenienceFee should not be null";
            }

            configMock.getConvenienceFeeTaxCode.mockReturnValueOnce(1);
            const result = convenienceFee.createConvenienceFeeInvoice(paymentMock, invoiceMock);
            expect(result).toEqual({
                "amount": 2.12,
                "id": undefined,
                "new": true,
                "oldAmount": null,
                "invoice": invoiceMock,
            });
        });

        describe ('returns no tax code in the source code', () => {
            test('empty list', () => {
                
                const convenienceFee = convenienceFeeFactory.getInstance(data);
                if (convenienceFee === null) {
                    throw "convenienceFee should not be null";
                }

                const result = convenienceFee.createConvenienceFeeInvoice(paymentMock, invoiceMock);
                expect(result).toEqual({
                    "amount": 2.12,
                    "id": undefined,
                    "new": true,
                    "invoice": invoiceMock,
                    "oldAmount": null,
                });
                expect(recordMock.load).toHaveBeenCalledTimes(0);
            });
            test('list no invoice', () => {
                
                const convenienceFee = convenienceFeeFactory.getInstance(data);
                if (convenienceFee === null) {
                    throw "convenienceFee should not be null";
                }
                recordMock.load.mockReturnValueOnce(null);

                convenienceFee.createConvenienceFeeInvoice(paymentMock, invoiceMock);
                expect(listSpy).toHaveBeenCalledTimes(0);
            });
            test('list with invoice', () => {
                
                const convenienceFee = convenienceFeeFactory.getInstance(data);
                if (convenienceFee === null) {
                    throw "convenienceFee should not be null";
                }
                context.context.getLineCount.mockReturnValueOnce(2);
                recordMock.load.mockReturnValueOnce(context.context);

                convenienceFee.createConvenienceFeeInvoice(paymentMock, invoiceMock);
            });
        });
    });
});