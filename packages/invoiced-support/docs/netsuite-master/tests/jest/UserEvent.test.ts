import objectFactory from './mappings';
import {Endpoint} from "../../src/Models/ConnectorFactory";

const contextAdapterMock = {
    getInstanceExtended: jest.fn(),
};

const ConnectorMock = {
    send: jest.fn(),
}

const ConnectorFactoryMock = {
    getInstance: () => ConnectorMock,
};
const logMock = {
    error: jest.fn(),
    debug: jest.fn(),
};

const SubsidiaryKeyMock = {
    get: () => 'test',
}

const userEventProvider = objectFactory('userEvent', {
    'N/log': logMock,
    'tmp/src/ContextAdapter': contextAdapterMock,
    'tmp/src/Models/ConnectorFactory': ConnectorFactoryMock,
    'tmp/src/Utilities/SubsidiaryKey': SubsidiaryKeyMock,
});

describe('After submit test', () => {
    test('Test non valid newRecord', () => {
        userEventProvider.afterSubmit({
            type: 'update',
            newRecord: null
        });
        const newRecordContext = {
            id: 1,
        };
        contextAdapterMock.getInstanceExtended.mockReturnValueOnce({
            getType: () => 'test',
        });
        userEventProvider.afterSubmit({
            type: 'delete',
            oldRecord: newRecordContext
        });
        expect(logMock.error).toHaveBeenCalledTimes(1);
    });

    test('Test delete', () => {
        logMock.error.mockClear();

        userEventProvider.afterSubmit({
            type: 'delete',
            newRecord: null,
            oldRecord: {
                id: 1,
                type: "random",
            },
        });
        expect(ConnectorMock.send).toHaveBeenCalledTimes(0);

        logMock.error.mockClear();
        const events: Record<string, Endpoint> = {
            'creditmemo': "credit_notes/accounting_sync",
            'customerpayment': "payments/accounting_sync",
            'invoice': "invoices/accounting_sync",
        }

        for (const i in events) {
            userEventProvider.afterSubmit({
                type: 'delete',
                newRecord: null,
                oldRecord: {
                    id: 1,
                    type: i,
                    getValue: () => 'test2',
                },
            });
            expect(ConnectorMock.send).toHaveBeenLastCalledWith('POST', events[i], 'test', {
                accounting_id: "1",
                accounting_system: "netsuite",
                deleted: true,
            });
        }
        expect(logMock.error).toHaveBeenCalledTimes(0);
    });
});