import 'module-alias/register';

import objectFactory, {context} from '../mappings';

describe('Contact test', () => {
    const addressMock = jest.fn();

    const listFactoryMock = {
        getInstance: addressMock,
    };

    const contactProvider = objectFactory('contact', {
        'tmp/src/Models/List': listFactoryMock,
    });


    test('Build Row', () => {
        let contact = contactProvider.getInstance(context);
        addressMock.mockReturnValueOnce({
            length: 0,
            map: null,
        });

        context.getValueString.mockImplementation(() => {
            return 'test';
        });
        let result = contact.buildRow();
        expect(result).toEqual({
            name: 'test',
            title: 'test',
            email: 'test',
            phone: 'test',
            primary: false,
        });

        addressMock.mockReturnValueOnce({
            length: 1,
            map: () =>[{
                address1: "test",
                address2: "test",
                city: "test",
                state: "test",
                postal_code: "test",
            }],
        });


        context.getValueString.mockImplementation((item) => {
            if (item === 'phone') {
                return null;
            }
            if (item === 'mobilephone') {
                return 'test1';
            }
            return 'test';
        });
        result = contact.buildRow();
        expect(result).toEqual({
            name: 'test',
            title: 'test',
            email: 'test',
            phone: 'test1',
            primary: false,
            address1: "test",
            address2: "test",
            city: "test",
            state: "test",
            postal_code: "test",
        });

        addressMock.mockReturnValueOnce({
            length: 1,
            map: () =>[{
                address1: "test",
                address2: "test",
                city: "test",
                state: "test",
                postal_code: "test",
            },{
                address1: "test2",
                address2: "test2",
                city: "test2",
                state: "test2",
                postal_code: "test2",
                primary: true,
            }],
        });


        context.getValueString.mockImplementation((item) => {
            if (item === 'phone' || item === 'mobilephone') {
                return null;
            }
            if (item === 'officephone') {
                return 'test2';
            }
            return 'test';
        });
        result = contact.buildRow();
        expect(result).toEqual({
            name: 'test',
            title: 'test',
            email: 'test',
            phone: 'test2',
            primary: true,
            address1: "test2",
            address2: "test2",
            city: "test2",
            state: "test2",
            postal_code: "test2",
        });
    });

});