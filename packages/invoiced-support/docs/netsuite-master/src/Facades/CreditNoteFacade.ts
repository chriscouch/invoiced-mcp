import * as Log from "N/log";
import {ConnectorFactoryInterface} from "../Models/ConnectorFactory";
import {InvoicedCreditNote} from "../definitions/InvoicedCreditNote";
import {HasNumberRow} from "../definitions/Row";
import {FacadeInterface} from "../definitions/FacadeInterface";

type defineCallback = (
    log: typeof Log,
    connectorFactory: ConnectorFactoryInterface) => FacadeInterface;

declare function define(arg: string[], fn: defineCallback): void;

/**
 *@NApiVersion 2.x
 *@NModuleScope Public
 *
 * This facade is used by customers
 * to be able to work with credit note inside
 * from their 3rd party user events.
 */
define(['N/log', 'tmp/src/Models/ConnectorFactory.js'], function (
    log,
    ConnectorFactory,
) {
    return {
        patch(key: null | string, data: HasNumberRow): null | InvoicedCreditNote {
            const connection = ConnectorFactory.getInstance();
            const response = connection.send('POST', 'credit_notes/accounting_sync', key, data);
            log.debug('Response', response);
            return response as null | InvoicedCreditNote;
        },
    }
});
