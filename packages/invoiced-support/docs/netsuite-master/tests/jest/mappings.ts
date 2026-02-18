// @ts-ignore
const debug: boolean = process.env.npm_lifecycle_script.indexOf("-VVV") !== -1;
// @ts-ignore
const audit: boolean = process.env.npm_lifecycle_script.indexOf("-VV") !== -1;
// @ts-ignore
const error: boolean = process.env.npm_lifecycle_script.indexOf("-V") !== -1;

const {
    loadSuiteScriptModule,
    NRecord,
    NSearch,
    NFile,
    NLog,
} = require('netsumo');

const record = new NRecord();
const search = new NSearch();
const file = new NFile();
export const log = new NLog();
log.debug = (a: string, b: string) => debug && console.debug(a, b);
log.audit = (a: string, b: string) => audit && console.info(a, b);
log.error = (a: string, b: string) => error && console.log(a, b);

//mocking create method
search.create = () => ({
    run: () => ({
        each: () => [],
    }),
});


const NetSuiteType = loadSuiteScriptModule('tmp/src/Models/NetSuiteType.js');
const netSuiteTypeCfg = {
    'N/record': record,
    'N/search': search,
    'N/log': log,
    'tmp/src/Models/ConnectorFactory': {},
}
export const netSuiteType = NetSuiteType(netSuiteTypeCfg);

const List = loadSuiteScriptModule('tmp/src/Models/List.js');
const listCfg = {
    'N/log': log,
}
export const list = List(listCfg);

const ContextAdapter = loadSuiteScriptModule('tmp/src/ContextAdapter.js');
const ContextAdapterCfg = {
    'N/log': log,
}
export const contextAdapter = ContextAdapter(ContextAdapterCfg);

const CustomMappings = loadSuiteScriptModule('tmp/src/Models/CustomMappings.js');
const customMappingsCfg = {
    'N/search': search,
    'N/log': log,
}
export const customMappings = CustomMappings(customMappingsCfg);

const render = {
    PrintMode: {
        PDF: 'pdf',
    },
    transaction: () => file.load({
        id: 'render',
    }),
}

export const advancedJobsHelper = {
    getCustomerId: jest.fn(),
}
advancedJobsHelper.getCustomerId.mockImplementation(item => {
    if (item === 123456) {
        return null;
    }
    return item
});


export const configUtilityMock = {
    doSendPDF: jest.fn(),
    doSyncInvoiceDrafts: jest.fn(),
    syncLineItemDates: jest.fn(),
};
const configFactory = {
    getInstance: () => configUtilityMock,
}

export const context = {
    context: {
        getLineCount: jest.fn(),
        getValue: jest.fn(),
        getSubrecord: jest.fn(),
        getSublistValue: jest.fn(),
        type: "test",

    },
    getValue: jest.fn(),
    getValueString: jest.fn(),
    getValueBoolean: jest.fn(),
    getId: jest.fn(),
    getType: jest.fn(),
};

const Customer = loadSuiteScriptModule('tmp/src/Models/Customer.js');
const CustomerCfg = {
    'N/record': record,
    'N/search': search,
    'N/log': log,
    'tmp/src/ContextAdapter': contextAdapter,
    'tmp/src/Models/NetSuiteType': netSuiteType,
    'tmp/src/Models/Contact': {},
    'tmp/src/Utilities/Address': {},
    'tmp/src/Models/List': list,
    'tmp/src/Models/CustomMappings': customMappings,
    'tmp/src/ConnectorFactory': {},
};
export const customer = Customer(CustomerCfg);

const subsidiaryKeyMock = {
    get: ()=> {
    },
}

const Invoice = loadSuiteScriptModule('tmp/src/Models/Invoice.js');
const invoiceCfg = {
    'N/record': record,
    'N/search': search,
    'N/render': render,
    'N/config': {},
    'N/file': file,
    'N/log': log,
    'tmp/src/Utilities/Address': {},
    'tmp/src/Models/ConnectorFactory': {},
    'tmp/src/Models/CustomMappings': customMappings,
    'tmp/src/Models/NetSuiteType': netSuiteType,
    'tmp/src/Utilities/AdvancedJobsHelper': advancedJobsHelper,
    'tmp/src/ContextAdapter': contextAdapter,
    'tmp/src/Models/Customer': customer,
    'tmp/src/Utilities/Config': configFactory,
    'tmp/src/Models/List': list,
    'tmp/src/Utilities/SubsidiaryKey': subsidiaryKeyMock,
};
export const invoice = Invoice(invoiceCfg);

const Contact = loadSuiteScriptModule('tmp/src/Models/Contact.js');
const contactCfg = {
    'N/search': record,
    'N/record': search,
    'N/log': log,
    'tmp/src/ContextAdapter': contextAdapter,
    'tmp/src/Models/NetSuiteType': netSuiteType,
    'tmp/src/Models/List': list,
    'tmp/src/Utilities/Address': {},
    'tmp/src/Models/CustomMappings': customMappings,
};
export const contact = Contact(contactCfg);

const CreditNote = loadSuiteScriptModule('tmp/src/Models/CreditNote.js');
const CreditNoteCfg = {
    'N/log': log,
    'tmp/src/Models/ConnectorFactory': {},
    'tmp/src/Models/CustomMappings': customMappings,
    'tmp/src/Models/NetSuiteType': netSuiteType,
};
export const creditNote = CreditNote(CreditNoteCfg);

const UserEventPayment = loadSuiteScriptModule('tmp/src/Models/UserEventPayment.js');
const UserEventPaymentCfg = {
    'N/log': log,
    'N/search': search,
    'N/record': record,
    'tmp/src/ContextAdapter': contextAdapter,
    'tmp/src/Models/List': list,
    'tmp/src/Models/ConnectorFactory': {},
    'tmp/src/Models/CustomMappings': customMappings,
    'tmp/src/Models/NetSuiteType': netSuiteType,
    'tmp/src/Models/Customer': customer,
    'tmp/src/Utilities/AdvancedJobsHelper': advancedJobsHelper,
};
export const userEventPayment = UserEventPayment(UserEventPaymentCfg);

const UserEvent = loadSuiteScriptModule('tmp/src/UserEvent.js');
const UserEventCfg = {
    'N/record': record,
    'N/log': log,
    'tmp/src/Models/CreditNote': creditNote,
    'tmp/src/Models/Invoice': invoice,
    'tmp/src/Models/UserEventPayment': userEventPayment,
    'tmp/src/ContextAdapter': contextAdapter,
};
export const userEvent = UserEvent(UserEventCfg);

const ConvenienceFee = loadSuiteScriptModule('tmp/src/Models/ConvenienceFee.js');
const ConvenienceFeeCfg = {
    'N/log': log,
    'N/record': record,
    'tmp/src/Models/List': list,
    'N/config': {},
};
export const convenienceFee = ConvenienceFee(ConvenienceFeeCfg);


const objectFactory = (type: string, overrides: object) =>
{
    if (!overrides) {
        overrides = {};
    }

    let cfg = {};
    switch (type) {
        case 'contact': cfg = contactCfg; break;
        case 'invoice': cfg = invoiceCfg; break;
        case 'customer': cfg = CustomerCfg; break;
        case 'netSuiteType': cfg = netSuiteTypeCfg; break;
        case 'userEventPayment': cfg = UserEventPaymentCfg; break;
        case 'userEvent': cfg = UserEventCfg; break;
        case 'ConvenienceFee': cfg = ConvenienceFeeCfg; break;
        default: throw "No such object " + type;
    }

    Object.assign(cfg, overrides);
    switch (type) {
        case 'contact': return Contact(cfg);
        case 'invoice': return Invoice(cfg);
        case 'customer': return Customer(cfg);
        case 'netSuiteType': return NetSuiteType(cfg);
        case 'userEventPayment': return UserEventPayment(cfg);
        case 'userEvent': return UserEvent(cfg);
        case 'ConvenienceFee': return ConvenienceFee(cfg);
    }
    throw "No such object " + type;
}

export default objectFactory;