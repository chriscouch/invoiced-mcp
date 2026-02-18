import 'module-alias/register';

import objectFactory, {context} from '../mappings';


describe('Customer test', () => {
    const accountNumberMock = jest.fn();
    const entityIdMock = jest.fn();
    const firstNameMock = jest.fn();
    const lastNameMock = jest.fn();
    const companynameMock = jest.fn();
    const altnameMock = jest.fn();
    const paymentTermMock = jest.fn();
    const isPersonMock = jest.fn();
    entityIdMock.mockReturnValue('eid');
    firstNameMock.mockReturnValue('firstName');
    lastNameMock.mockReturnValue('lastName');
    companynameMock.mockReturnValue('Company Name');
    accountNumberMock.mockReturnValue(null);
    altnameMock.mockReturnValue('altname');
    paymentTermMock.mockReturnValue(null);
    isPersonMock.mockReturnValue(true);
    context.getValue.mockImplementation((item) => {
        switch (item) {
            case 'firstName': return firstNameMock();
            case 'lastName': return lastNameMock();
            case 'altname': return altnameMock();
            case 'entityid': return entityIdMock();
            case 'companyname': return companynameMock();
            case 'accountnumber': return accountNumberMock();
            case 'terms': return paymentTermMock();
        }
        return null;
    });
    context.getId.mockReturnValue(2);
    context.getValueBoolean.mockImplementation((item) => {
        switch (item) {
            case 'isperson': return isPersonMock();
        }
        return null;
    });

    const connectorFactoryMock = {
        getInstance: () => ({
            send: (_method: string, params: string) => {
                switch (params) {
                    case "customers?&filter[number]=1": return '[{"id": 1}]';
                    case "customers?per_page=1&filter[name]=name": return "[]";
                    case "customers?per_page=1&filter[name]=name2": return '[{"id": 2}]';
                }
                return null;
            },
        }),
    };

    const searchMock = {
        lookupFields: () => ({
            name: "Due in 30 days",
        }),
        Type: {
            TERM: 'term',
        }
    };

    const ContactSearchFactory = {
        getInstance: () => ({
            run: () => ({
                each: (input: any) => input,
            })
        })
    }

    const customerProvider = objectFactory('customer', {
        'N/search': searchMock,
        'tmp/src/Models/ConnectorFactory': connectorFactoryMock,
        'tmp/src/Scheduled/Searches/ContactSearch': ContactSearchFactory,
    });

    let customer = customerProvider.getInstance(context);

    describe('Person vs Company', () => {

        test('Person ', () => {

            expect(customer.getType()).toEqual('person');

            customer = customerProvider.getInstance(context);
            expect(customer.getType()).toEqual('person');

            let row = customer.buildRow();
            expect(row).toEqual({
                "accounting_id": "2",
                "accounting_system": "netsuite",
                "active": true,
                "contacts": [],
                "metadata": {},
                "name": "firstName lastName",
                "number": 'eid',
                "payment_terms": null,
                "phone": null,
                "tax_id": null,
                "type": "person",
            });

            lastNameMock.mockReturnValue(null);
            row = customer.buildRow();
            expect(row).toEqual({
                "accounting_id": "2",
                "accounting_system": "netsuite",
                "active": true,
                "contacts": [],
                "metadata": {},
                "name": "firstName",
                "number": 'eid',
                "payment_terms": null,
                "phone": null,
                "tax_id": null,
                "type": "person",
            });

            firstNameMock.mockReturnValue(null);
            row = customer.buildRow();
            expect(row).toEqual({
                "accounting_id": "2",
                "accounting_system": "netsuite",
                "active": true,
                "contacts": [],
                "metadata": {},
                "name": 'altname',
                "number": 'eid',
                "payment_terms": null,
                "phone": null,
                "tax_id": null,
                "type": "person",
            });

            altnameMock.mockReturnValue(null);
            row = customer.buildRow();
            expect(row).toEqual({
                "accounting_id": "2",
                "accounting_system": "netsuite",
                "active": true,
                "contacts": [],
                "metadata": {},
                "name": 'eid',
                "number": 'eid',
                "payment_terms": null,
                "phone": null,
                "tax_id": null,
                "type": "person",
            });
        });

        test('Company ', () => {
            isPersonMock.mockReturnValue(false);
            companynameMock.mockReturnValue('Company Name');
            accountNumberMock.mockReturnValue('   111  ');
            companynameMock.mockReturnValue(null);

            customer = customerProvider.getInstance(context);
            const row = customer.buildRow();
            expect(row).toEqual({
                "accounting_id": "2",
                "accounting_system": "netsuite",
                "active": true,
                "contacts": [],
                "metadata": {},
                "name": "eid",
                "number": "111",
                "payment_terms": null,
                "phone": null,
                "tax_id": null,
                "type": "company",
            });
        });
    });

    test('Customer', () => {
        expect(customer.getUrl()).toEqual('customers/accounting_sync');

        expect(customer.getPaymentTerm()).toEqual(null);
        paymentTermMock.mockReturnValue("test");
        expect(customer.getPaymentTerm()).toEqual('NET 30');

        expect(customer.buildRow()).toEqual({
            "accounting_id": "2",
            "accounting_system": "netsuite",
            "active": true,
            "contacts": [],
            "metadata": {},
            "name": "eid",
            "number": "111",
            "payment_terms": "NET 30",
            "phone": null,
            "tax_id": null,
            "type": "company",
        });
    });

});
