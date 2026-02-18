import * as NRecord from "N/record";
import * as Log from "N/log";
import * as NSearch from "N/search";
import {TransactionRestletInputParameters} from "../definitions/TransactionRestletInputParameters";
import {CustomerMatcherInterface} from "./CustomerMatcher";
import * as NTransaction from "N/transaction";
import {ValueSetterInterface} from "./ValueSetter";
import {FacadeInterface} from "../definitions/FacadeInterface";
import {RestletOutput} from "../definitions/RestletIOutput";
import {DivisionKeyInterface} from "../Utilities/SubsidiaryKey";

type defineCallback = (
    record: typeof NRecord,
    search: typeof NSearch,
    log: typeof Log,
    transaction: typeof NTransaction,
    subsidiaryKey: DivisionKeyInterface,
    CustomerMatcher: CustomerMatcherInterface,
    ValueSetter: ValueSetterInterface
) => SaveTransactionFactoryInterface;

declare function define(arg: string[], fn: defineCallback): void;

export interface SaveTransactionFactoryInterface {
    create(
        input: TransactionRestletInputParameters,
        facade: FacadeInterface,
        type: NSearch.Type,
    ): RestletOutput|NRecord.Record;
}

/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define([
    'N/record',
    'N/search',
    'N/log',
    'N/transaction',
    'tmp/src/Utilities/SubsidiaryKey',
    'tmp/src/Libs/CustomerMatcher',
    'tmp/src/Libs/ValueSetter',
], function (record,
             search,
             log,
             transaction,
             SubsidiaryKey,
             CustomerMatcher,
             ValueSetter) {

    const RESTRICTED_FIELDS = ['id'];

    function create(
        input: TransactionRestletInputParameters,
        facade: FacadeInterface,
        type: NSearch.Type,
    ): RestletOutput|NRecord.Record {
        log.debug('Initiated ' + type, input);

        if (input.netsuite_id) {
            if (input.voided) {
                transaction.void({
                    type: type.toString(),
                    id: input.netsuite_id
                });
                log.debug('Transaction voided', input.netsuite_id);

                return response('Transaction voided', 406);
            }

            log.debug('Existing Transaction', 'exiting');
            return response('Existing Transaction', 406);
        }


        const match = findTransaction(input.tranid, type);
        if (match) {
            //mapping not set and not a job
            facade.patch(SubsidiaryKey.get(match.subsidiary), {
                accounting_system: 'netsuite',
                accounting_id: match.id.toString(),
                number: input.tranid,
            });

            return response('Existing Transaction', 406);
        }

        let parentCustomerId: number | null = null;
        log.debug('Parent Customer match found', parentCustomerId);
        if (input.parent_customer) {
            parentCustomerId = CustomerMatcher.getCustomerIdDecorated(
                input.parent_customer.accountnumber,
                input.parent_customer.companyname,
                input.parent_customer.netsuite_id,
            );

            log.debug('Parent Customer match found', parentCustomerId);
        }

        if (!parentCustomerId) {
            log.debug('error', 'No parent customer found');
            return response('No parent customer found', 406);
        }

        if (type === search.Type.INVOICE) {
            const model = record.transform({
                fromType: record.Type.CUSTOMER,
                fromId: parentCustomerId,
                toType: record.Type.INVOICE,
                isDynamic: true,
            });
            return save(model, input);
        }

        const model = record.create({
            type: record.Type.CREDIT_MEMO,
            isDynamic: true,
        });
        model.setValue({
            fieldId: 'entity',
            value: parentCustomerId,
        });

        return save(model, input);
    }


    function findTransaction(tranid: string, type: NSearch.Type): {
        id: number,
        subsidiary: number
    } | null {
        const result = search.create({
            type: type.toString(),
            filters: [["tranid","is", tranid], "AND", ['mainline', search.Operator.IS, 'T']],
            columns: ['subsidiary']
        }).run().getRange({
            start: 0,
            end: 2
        });
        log.debug('Transaction Result', result);
        if (result.length != 1 || !result[0]) {
            return null;
        }

        return {
            id: parseInt(result[0].id),
            subsidiary: parseInt(result[0].getValue({name: 'subsidiary'}).toString()),
        };
    }


    function save(
        record: NRecord.Record,
        params: TransactionRestletInputParameters,
    ): NRecord.Record {
        params.currencysymbol = params.currencysymbol.toUpperCase();
        if (!params.taxitem) {
            if (params.taxlineitem) {
                params.items.push({
                    item: params.taxlineitem,
                    quantity: 1,
                    rate: params.taxrate || 0,
                    description: 'Invoiced Calculated Tax',
                });
            }
            delete params.taxrate;
        }
        if (!params.discountitem) {
            delete params.discountrate;
        }
        ValueSetter.set(record, params, RESTRICTED_FIELDS);
        log.debug('Line items to be saved', params.items);
        ValueSetter.setSublist(record, params.items, 'item', []);

        record.save({
            enableSourcing: true,
            ignoreMandatoryFields: true,
        });
        log.audit('Transaction successfully saved', record);

        return record;
    }

    function response(message: string, status: number): RestletOutput {
        return {
            message: message,
            status: status,
        }
    }

    return {
        create: create,
    }
});
