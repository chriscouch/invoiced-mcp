import * as NRecord from "N/record";
import * as Log from "N/log";
import {FieldValue} from "N/record";
import {InvoicedPaymentApplication} from "../PaymentsModelScriptlet";
import {ListFactoryInterface} from "../definitions/List";
import {ConfigFactory, ConfigInterface} from "../Utilities/Config";


export interface ConvenienceFeeFactoryInterface {
    getInstance(applied: InvoicedPaymentApplication[]): null | ConvenienceFeeInterface;
}

export interface ConvenienceFeeInterface {
    findExistingConvenienceFee(payment: NRecord.Record): null | ConvenienceFeeInvoice;
    createConvenienceFeeInvoice(payment: NRecord.Record, invoice: NRecord.Record): null | ConvenienceFeeInvoice;
}

export type ConvenienceFeeInvoice = {
    id: number,
    amount: number,
    new: boolean,
    oldAmount: null | number,
    invoice: NRecord.Record,
}

type defineCallback = (
    log: typeof Log,
    record: typeof NRecord,
    List: ListFactoryInterface,
    global: typeof Globals,
    Config: ConfigFactory) => ConvenienceFeeFactoryInterface;

declare function define(arg: string[], fn: defineCallback): void;

/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define([
    'N/log',
    'N/record',
    'tmp/src/Models/List',
    'tmp/src/Global',
    'tmp/src/Utilities/Config',
], function(
    log,
    record,
    List,
    global,
    ConfigFactory,
) {
    class ConvenienceFee implements ConvenienceFeeInterface {
        constructor(
            private config: ConfigInterface,
            private convenienceFee: FieldValue,
            private convenienceFeeApplication: InvoicedPaymentApplication) {
            log.debug('convenienceFee', this);
        }


        private getConvenienceFeeTaxCode(invoice: null|NRecord.Record): null | string {
            let convenienceFeeTaxCode = this.config.getConvenienceFeeTaxCode();
            if (convenienceFeeTaxCode) {
                return convenienceFeeTaxCode.toString();
            }
            if (!invoice) {
                return null;
            }
            List.getInstance(invoice, 'item').map(function (list2) {
                convenienceFeeTaxCode = list2.getValueString('taxcode');
                return false;
            });
            return convenienceFeeTaxCode ? convenienceFeeTaxCode.toString() : null;
        }

        public createConvenienceFeeInvoice(payment: NRecord.Record, initialInvoice: null|NRecord.Record): null | ConvenienceFeeInvoice {
            const convenienceFeeTaxCode = this.getConvenienceFeeTaxCode(initialInvoice);
            const subsidiaryId = initialInvoice?.getValue('subsidiary') ?? payment.getValue('subsidiary');
            log.debug('convenience fee source subsidiaryId', subsidiaryId);
            const invoice = record.create({
                type: record.Type.INVOICE,
                isDynamic: true,
                defaultValues: {
                    subsidiary: subsidiaryId,
                    entity: initialInvoice?.getValue('entity') ?? payment.getValue('customer')
                }
            });
            const accountId = initialInvoice?.getValue('account') ?? payment.getValue('aracct');
            log.debug('Source accountId', accountId);
            invoice.setValue({
                fieldId: 'currency',
                value: payment.getValue('currency'),
            });
            invoice.setValue({
                fieldId: 'account',
                value: accountId,
            });
            invoice.setValue({
                fieldId: global.INVOICED_RESTRICT_SYNC_TRANSACTION_BODY_FIELD,
                value: true,
                ignoreFieldChange: true,
            });
            invoice.setValue({
                fieldId: 'amount',
                value: this.convenienceFeeApplication.amount,
                ignoreFieldChange: true,
            });
            invoice.selectNewLine({
                sublistId: 'item',
            });

            try {
                invoice.setCurrentSublistValue({
                    sublistId: 'item',
                    fieldId: 'item',
                    value: this.convenienceFee,
                });
            } catch (e: any) {
                if (e.name === 'INVALID_FLD_VALUE') {
                    throw 'Wrong convenience fee line item was specified. The line item should be set as Non inventory sales item and enabled for all subsidiaries you want to sync';
                }
                throw e;
            }
            invoice.setCurrentSublistValue({
                sublistId: 'item',
                fieldId: 'taxcode',
                value: convenienceFeeTaxCode,
            });
            invoice.setCurrentSublistValue({
                sublistId: 'item',
                fieldId: 'amount',
                value: this.convenienceFeeApplication.amount,
            });
            try {
                invoice.commitLine({
                    sublistId: 'item',
                });
                log.debug('Convenience fee invoice to be created', invoice);
                const id = invoice.save({
                    enableSourcing: true,
                    ignoreMandatoryFields: true,
                });
                return {
                    id: id,
                    amount: this.convenienceFeeApplication.amount,
                    oldAmount: null,
                    new: true,
                    invoice: invoice,
                };
            } catch (e: any) {
                if (e.name === 'TRANS_UNBALNCD') {
                    throw 'Convenience fee should not be taxable. Please set the zero tax rate code in the Company';
                }
                throw e;
            }
        }

        public findExistingConvenienceFee(payment: NRecord.Record): null | ConvenienceFeeInvoice {
            let feeInvoice: null | ConvenienceFeeInvoice = null;
            const that = this;
            List.getInstance(payment, 'apply').map(function (list2) {
                log.debug('Found invoice matching convenience fee criteria', list2);
                const invoiceId = list2.getValueNumber('doc');
                if (!invoiceId) {
                    return true;
                }
                const invoice = record.load({
                    type: record.Type.INVOICE,
                    id: invoiceId,
                    isDynamic: true,
                });
                let ItemHasConvenienceFee: boolean | null = false;
                let oldAmount: number | null = null;
                List.getInstance(invoice, 'item').map(function (list3) {
                    if (list3.get('internalId') === that.convenienceFee) {
                        ItemHasConvenienceFee = true;
                        oldAmount = list3.getValueNumber('amount');
                        return false;
                    }
                    return true;
                });
                if (ItemHasConvenienceFee) {
                    feeInvoice = {
                        id: invoiceId,
                        oldAmount: oldAmount,
                        amount: that.convenienceFeeApplication.amount,
                        new: false,
                        invoice: invoice,
                    };
                    return false;
                }
                return true;
            });
            return feeInvoice;
        }
    }

    return {
        getInstance: function(applied: InvoicedPaymentApplication[]) {
            if (!applied.length) {
                return null;
            }
            const config = ConfigFactory.getInstance();
            const convenienceFee = config.getConvenienceFee();
            if (!convenienceFee) {
                return null;
            }
            const convenienceFeeApplication = applied.filter(item => item.type === 'convenience_fee').shift();
            if (!convenienceFeeApplication) {
                return null;
            }
            log.debug('convenienceFee', convenienceFee);

            return new ConvenienceFee(config, convenienceFee, convenienceFeeApplication);
        },
    };
});