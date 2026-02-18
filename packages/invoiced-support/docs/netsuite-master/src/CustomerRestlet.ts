import * as NRecord from "N/record";
import * as Log from "N/log";
import * as NSearch from "N/search";
import {
    CustomerRestletInputParameters,
    SaveCustomerFactoryInterface,
    SaveCustomerInterface
} from "./Libs/SaveCustomerFactory";
import {RecordToJSONReturnValue} from "N/record";
import {ValueSetterInterface} from "./Libs/ValueSetter";


type defineCallback = (
    record: typeof NRecord,
    search: typeof NSearch,
    log: typeof Log,
    SaveCustomerFactory: SaveCustomerFactoryInterface,
    ValueSetter: ValueSetterInterface
) => object;

declare function define(arg: string[], fn: defineCallback): void;


/**
 * @NApiVersion 2.x
 * @NScriptType Restlet
 * @NModuleScope Public
 */
define([
    'N/record',
    'N/search',
    'N/log',
    'tmp/src/Libs/SaveCustomerFactory',
    'tmp/src/Libs/ValueSetter',
], function (record,
             search,
             log,
             SaveCustomerFactory,
             ValueSetter
) {

    const RESTRICTED_FIELDS = ['id'];

    class CustomerRestlet {
        private save(
            customer: NRecord.Record,
            params: CustomerRestletInputParameters,
        ): NRecord.Record {
            if (params.currencysymbol) {
                const currencies = search.create({
                    type: search.Type.CURRENCY,
                    filters: [search.createFilter({
                        name: 'symbol',
                        operator: search.Operator.IS,
                        values: params.currencysymbol.toUpperCase()
                    })]
                }).run().getRange({start: 0, end: 1});


                if (currencies) {
                    //clear currencies
                    var sublistObj = customer.getSublist({ sublistId: 'currency' });
                    var itemCount = sublistObj.lineCount;

                    for (var i = 0; i < itemCount; i++) {
                        customer.selectLine({sublistId: 'currency', line: i});
                        customer.setCurrentSublistValue({
                            sublistId: 'currency',
                            fieldId: 'currency',
                            value: null
                        });
                        customer.commitLine({sublistId: 'currency'});
                    }
                    params.currency = currencies[0].id;
                }
                delete params.currencysymbol;
            }

            for (let i in params) {
                if (i === 'active') {
                    const value = params[i];
                    i = 'isinactive';
                    params[i] = !value;
                    delete params.active;
                } else if (i === 'type') {
                    const value = params[i];
                    i = 'isperson';
                    params[i] = value === 'person' ? 'T' : 'F';
                    delete params.type;
                }
            }

            ValueSetter.set(customer, params, RESTRICTED_FIELDS);

            //special overrides
            this.updateAddress(customer, params);

            customer.save({
                enableSourcing: true,
                ignoreMandatoryFields: true,
            });
            log.audit('Customer successfully saved', customer);

            return customer;
        }


        private updateAddress(customer: NRecord.Record, address: CustomerRestletInputParameters) {
            log.debug('address to be saved', address);
            if (!address.addr1 || !address.city || !address.state || !address.country || !address.zip) {
                return;
            }
            const currentAddressCount = customer.getLineCount({
                'sublistId': 'addressbook'
            });

            log.debug('currentAddressCount', currentAddressCount);

            if (currentAddressCount === 0){
                customer.selectNewLine({
                    sublistId: 'addressbook'
                });
            } else if (currentAddressCount === 1){
                customer.selectLine({
                    sublistId: 'addressbook',
                    line: 0
                });
            } else {
                return;
            }

            const addressSubrecord = customer.getCurrentSublistSubrecord({
                sublistId: 'addressbook',
                fieldId: 'addressbookaddress'
            });

            ValueSetter.set(addressSubrecord, address, []);

            customer.commitLine({
                sublistId: 'addressbook'
            });
        }


        public doPost(input: CustomerRestletInputParameters) {
            log.debug('Called from POST', input);

            let model: SaveCustomerInterface;
            model = SaveCustomerFactory.create(input);
            const existing = !input.netsuite_id && Boolean(model.customer.id);

            log.debug('Existing Customer', existing);

            const respCustomer = this.save(model.customer, input);

            const response: RecordToJSONReturnValue & {
                existing?: boolean
            } = respCustomer.toJSON();
            response.existing = existing;

            return response;
        }
    }


    return {
        post: (params: CustomerRestletInputParameters) => (new CustomerRestlet()).doPost(params),
        put: (params: CustomerRestletInputParameters) => (new CustomerRestlet()).doPost(params),
    };
});