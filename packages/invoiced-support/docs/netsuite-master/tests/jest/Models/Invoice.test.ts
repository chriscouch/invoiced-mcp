import objectFactory, {configUtilityMock, context, customMappings} from '../mappings';

test('Attachment mapping test', () => {
    const connectorFactoryMock = {
        getInstance: () => ({
            sendFile: (files: string[]) => ({
                id: files[0],
            }),
        }),
    };

    const customerMock = {
        buildRow: () => ({}),
    }
    const customerFactoryMock = {
        getInstance: () => customerMock,
    }

    const InvoiceSimpleProvider = objectFactory('invoice', {
        'tmp/src/Models/ConnectorFactory': connectorFactoryMock,
        'tmp/src/Models/Customer': customerFactoryMock,
    }) ;

    context.getId.mockReturnValue(1);
    context.getValue.mockImplementation((key) => {
        // console.log(key);
        switch (key) {
            case 'entity': return 2;
            case 'currencysymbol': return 'usd';
        }
        return null;
    });
    context.context.type = "invoice";
    configUtilityMock.doSyncInvoiceDrafts.mockReturnValueOnce(false);
    configUtilityMock.syncLineItemDates.mockReturnValueOnce(false);

    let invoice = InvoiceSimpleProvider.getInstance(context);
    invoice.shouldSync = () => true;
    let row = invoice.buildRow();
    expect(row.attachments).toBe(undefined);
    expect(row.pdf_attachment).toBe(undefined);

    const customMappingsInstance = customMappings.getInstance();

    invoice = InvoiceSimpleProvider.getInstance(context);
    customMappingsInstance.mappings.invoice_attachment = {'1': null};
    //mocking file id to be returned
    context.getValue.mockImplementation((item) => {
        if (item == '1' || item == '2') {
            return item;
        }
        return 1;
    });
    context.context.getLineCount.mockReturnValue(0);
    row = invoice.buildRow();
    expect(row.attachments.length).toBe(1);
    expect(row.attachments).toEqual([
        { value: { url: '/core/media/media.nl?id=1' }, name: 'file' },
    ]);
    expect(row.pdf_attachment).toEqual(row.attachments[0]);

    customMappingsInstance.mappings.invoice_attachment = {'1': null, '2': null};
    row = invoice.buildRow();
    expect(row.attachments.length).toBe(2);
    expect(row.attachments).toEqual([
        { value: { url: '/core/media/media.nl?id=1' }, name: 'file' },
        { value: { url: '/core/media/media.nl?id=2' }, name: 'file' },
    ]);
    expect(row.pdf_attachment).toEqual(row.attachments[0]);

    configUtilityMock.doSendPDF.mockReturnValueOnce(true);
    row = invoice.buildRow();
    expect(row.attachments.length).toBe(3);
    expect(row.attachments).toEqual([
        { value: { url: '/core/media/media.nl?id=render' }, name: 'file' },
        { value: { url: '/core/media/media.nl?id=1' }, name: 'file' },
        { value: { url: '/core/media/media.nl?id=2' }, name: 'file' },
    ]);
    expect(row.pdf_attachment).toEqual(row.attachments[0]);
});