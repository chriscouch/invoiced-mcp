import {EntryPoints} from "N/types";
import {CustomerFactoryInterface} from "./Models/Customer";
/**
 * @NApiVersion 2.x
 * @NScriptType Suitelet
 * @NModuleScope Public
 */
type defineCallback = (
    contact: CustomerFactoryInterface,
) => object;

declare function define(arg: string[], fn: defineCallback): void;

define([
    'tmp/src/Models/Customer',
], function(
    CustomerFactory,) {
    function onRequest(context: EntryPoints.Suitelet.onRequestContext): void {
        const customerId: number = context.request.parameters.customer_id;
        CustomerFactory.markForUpdate(customerId);
    }
    return {
        onRequest: onRequest
    };
});
