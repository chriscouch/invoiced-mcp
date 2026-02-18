import * as NSearch from "N/search";
import {
    TransactionRestletInputParameters
} from "./definitions/TransactionRestletInputParameters";
import {SaveTransactionFactoryInterface} from "./Libs/SaveTransactionFactory";
import {FacadeInterface} from "./definitions/FacadeInterface";


type defineCallback = (
    search: typeof NSearch,
    SaveTransactionFactory: SaveTransactionFactoryInterface,
    facade: FacadeInterface,
) => object;

declare function define(arg: string[], fn: defineCallback): void;


/**
 * @NApiVersion 2.x
 * @NScriptType Restlet
 * @NModuleScope Public
 */
define([
    'N/search',
    'tmp/src/Libs/SaveTransactionFactory',
    'tmp/src/Facades/InvoiceFacade',
], function (
             search,
             saveTransactionFactory,
             facade,
) {


    function doPost(input: TransactionRestletInputParameters) {
        return saveTransactionFactory.create(input, facade, search.Type.INVOICE);
    }


    return {
        post: doPost,
        put: doPost,
    };
});