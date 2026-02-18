import * as Log from "N/log";
import * as NSearch from "N/search";
import {CreateSearchFilterOptions} from "N/search";
import {FacadeInterface} from "../definitions/FacadeInterface";
import {AdvancedJobsHelperInterface} from "../Utilities/AdvancedJobsHelper";
import {DivisionKeyInterface} from "../Utilities/SubsidiaryKey";

type defineCallback = (
    search: typeof NSearch,
    log: typeof Log,
    CustomerFacade: FacadeInterface,
    subsidiaryKey: DivisionKeyInterface,
    advancedJobsHelper: AdvancedJobsHelperInterface) => CustomerMatcherInterface;

declare function define(arg: string[], fn: defineCallback): void;

export interface CustomerMatcherInterface {
    getCustomerIdDecorated(
        customer_number: string,
        customer_name: string,
        customer: null | number,
    ): null | number;
}


type BuildQueryParameter = {
    key: string,
    operator: NSearch.Operator,
    value: undefined | string | Array<any>,
}


/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define([
    'N/search',
    'N/log',
    'tmp/src/Facades/CustomerFacade',
    'tmp/src/Utilities/SubsidiaryKey',
    'tmp/src/Utilities/AdvancedJobsHelper',
], function (search,
             log,
             CustomerFacade,
             SubsidiaryKey,
             advancedJobsHelper) {


    function getCustomerIdDecorated(
        customer_number: string,
        customer_name: string,
        customer: null | number,
    ): null | number {
        if (!customer) {
            let entity = findCustomerByNumber(
                customer_number,
                customer_name
            );

            if (!entity) {
                return null;
            }

            CustomerFacade.patch(SubsidiaryKey.get(entity.subsidiary), {
                accounting_system: 'netsuite',
                accounting_id: entity.id.toString(),
                number: customer_number,
            });

            customer = entity.id;
        }

        return advancedJobsHelper.getCustomerId(customer);
    }



    function findCustomerByNumber(
        customer_number: string,
        customer_name: string,
    ): {
        id: number,
        subsidiary: number
    } | null {
        let qry = buildSearchCondition({
            key: 'accountnumber',
            operator: search.Operator.IS,
            value: customer_number,
        }, {
            key: 'entityid',
            operator: search.Operator.IS,
            value: customer_number,
        });
        if (qry.length) {
            const customer = findCustomer(qry);
            if (customer) {
                return customer;
            }
        }

        if (!customer_name) {
            return null;
        }
        const name = customer_name;
        let parts: Array<string | undefined> = name.split(' ', 2);
        if (parts.length < 2) {
            parts[1] = parts[0];
            parts[0] = undefined;
        }

        qry = buildSearchCondition({
            key: 'firstname',
            operator: search.Operator.IS,
            value: parts[0],
        }, {
            key: 'lastname',
            operator: search.Operator.IS,
            value: parts[1],
        });
        qry = buildSearchCondition({
            key: 'companyname',
            operator: search.Operator.IS,
            value: name,
        }, qry);
        if (qry.length) {
            const customer = findCustomer(qry);
            if (customer) {
                return customer;
            }
        }

        qry = buildSearchCondition({
            key: 'entityid',
            operator: search.Operator.IS,
            value: customer_name,
        }, {
            key: 'altname',
            operator: search.Operator.IS,
            value: customer_name,
        });
        return qry.length ? findCustomer(qry) : null;
    }




    function findCustomer(filter: CreateSearchFilterOptions[] | any[]): {
        id: number,
        subsidiary: number
    } | null {
        log.debug('Customer Filter', filter);
        const nSCustomers = search.create({
            type: search.Type.CUSTOMER,
            filters: filter,
            columns: ['subsidiary'],
        }).run().getRange({
            start: 0,
            end: 2
        });
        log.debug('Customer Result', nSCustomers);
        if (nSCustomers.length != 1 || !nSCustomers[0]) {
            return null;
        }

        return {
            id: parseInt(nSCustomers[0].id),
            subsidiary: parseInt(nSCustomers[0].getValue({name: 'subsidiary'}).toString()),
        };
    }


    function buildSearchCondition(parameter1: BuildQueryParameter, parameter2: Array<any> | BuildQueryParameter): Array<any> {
        const item1 = parameter1.value ? [parameter1.key, parameter1.operator, parameter1.value] : null;
        let item2 = null;
        if (Array.isArray(parameter2) && parameter2.length > 0) {
            item2 = parameter2;
        } else {
            const par2 = parameter2 as BuildQueryParameter;
            if (par2.value) {
                item2 = [par2.key, par2.operator, par2.value];
            }
        }
        if (item1 && item2) {
            return [item1, 'or', item2];
        }
        return item1 || item2 || [];
    }


    return {
        getCustomerIdDecorated: (
            customer_number: string,
            customer_name: string,
            customer: null | number,
        ) => getCustomerIdDecorated(customer_number, customer_name, customer),
    }
});