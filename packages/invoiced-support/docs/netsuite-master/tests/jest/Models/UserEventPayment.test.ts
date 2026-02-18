import objectFactory, {context} from '../mappings';

const amount = 600;
const appliedTo = [
    {
        amount: 100,
        type: 'invoice',
        invoice: {test: 'test'},
    }, {
        amount: 200,
        type: 'invoice',
        invoice: {test: 'test'},
    }, {
        amount: 300,
        type: 'invoice',
        invoice: {test: 'test'},
    },
];

const invoiceSimpleProviderMock = {
    getInstance: jest.fn(),
};

const customerMock = {
    buildRow: () => ({
        "accounting_id": "2",
        "accounting_system": "netsuite",
        "active": true,
        "metadata": {},
        "name": "INVD-0001",
        "number": "INVD-0001",
        "type": "company",
    }),
}
const customerFactoryMock = {
    getInstance: () => customerMock,
}
const invoiceMock = {
    buildRow: () => ({"test": "test",}),
    shouldSync: () => true,
}
const invoiceFactoryMock = {
    getInstance: () => invoiceMock,
}

const contextAdapterMock = {
    getInstanceDynamicExtended: jest.fn(),
    getInstanceExtended: jest.fn(),
}


const userEventPaymentProvider = objectFactory('userEventPayment', {
    'tmp/src/Models/Customer': customerFactoryMock,
    'tmp/src/Models/Invoice': invoiceFactoryMock,
    'tmp/src/Providers/InvoiceSimpleProvider': invoiceSimpleProviderMock,
    'tmp/src/ContextAdapter': contextAdapterMock,
});

context.getId.mockReturnValue(1);
context.getValue.mockImplementation((item) => {
    if (item === 'customer') {
        return 2;
    }
    if (item === 'applied') {
        return 900;
    }
    if (item === 'trandate') {
        return '10/10/2022';
    }
    return 1;
});
invoiceSimpleProviderMock.getInstance.mockReturnValue({
    buildRow: () => ({test: 'test'}),
})

context.context.getSublistValue.mockReturnValueOnce(true);
context.context.getSublistValue.mockReturnValueOnce(100);
context.context.getSublistValue.mockReturnValueOnce(1);
context.context.getSublistValue.mockReturnValueOnce(true);
context.context.getSublistValue.mockReturnValueOnce(200);
context.context.getSublistValue.mockReturnValueOnce(1);
context.context.getSublistValue.mockReturnValueOnce(true);
context.context.getSublistValue.mockReturnValueOnce(300);
context.context.getSublistValue.mockReturnValueOnce(1);
//should not add
//not apply
context.context.getSublistValue.mockReturnValueOnce(false);
//no amount
context.context.getSublistValue.mockReturnValueOnce(true);
context.context.getSublistValue.mockReturnValueOnce(300)
//no id
context.context.getSublistValue.mockReturnValueOnce(true);
context.context.getSublistValue.mockReturnValueOnce(300);
context.context.getSublistValue.mockReturnValueOnce(null);


test('User Event Payment test', () => {

    const userEventPayment = userEventPaymentProvider.getInstance(context);

    context.context.getLineCount.mockReturnValue(6);
    context.context.type = 'customerpayment';
    const row = userEventPayment.buildRow();
    expect(row).toEqual({
        "amount": amount,
        "accounting_id": "1",
        "accounting_system": "netsuite",
        "applied_to": appliedTo,
        "currency": "1",
        "customer": {
            "accounting_id": "2",
            "accounting_system": "netsuite",
            "active": true,
            "metadata": {},
            "name": "INVD-0001",
            "number": "INVD-0001",
            "type": "company",
        },
        "date": new Date('10/10/2022').getTime() / 1000,
        "metadata": {},
        "number": "1",
        "voided": true,
    });
});