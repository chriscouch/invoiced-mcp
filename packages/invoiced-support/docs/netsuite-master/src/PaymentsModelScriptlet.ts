import * as NRecord from "N/record";
import * as Log from "N/log";
import * as NSearch from "N/search";
import * as NTransaction from "N/transaction";
import {ConfigFactory} from "./Utilities/Config";
import {DateUtilitiesInterface} from "./Utilities/DateUtilities";
import {ConvenienceFeeInvoice} from "./Models/ConvenienceFee";
import {ListFactoryInterface} from "./definitions/List";
import {PaymentDepositInterface} from "./PaymentDeposit";
import {FacadeInterface} from "./definitions/FacadeInterface";
import {SavePaymentFactoryInterface, SavePaymentInterface} from "./Libs/SavePaymentFactory";
import {RestletInputParameters} from "./definitions/RestletInputParameters";
import {DivisionKeyInterface} from "./Utilities/SubsidiaryKey";


type defineCallback = (
    record: typeof NRecord,
    search: typeof NSearch,
    log: typeof Log,
    transaction: typeof NTransaction,
    list: ListFactoryInterface,
    paymentDeposit: PaymentDepositInterface,
    InvoiceFacade: FacadeInterface,
    CreditNoteFacade: FacadeInterface,
    Config: ConfigFactory,
    dateUtilities: DateUtilitiesInterface,
    global: typeof Globals,
    SavePaymentFactory: SavePaymentFactoryInterface,
    subsidiaryKey: DivisionKeyInterface,
    ) => object;

declare function define(arg: string[], fn: defineCallback): void;

export type InvoicedPaymentApplication = {
    invoice_netsuite_id: null | number;
    type: "applied_credit" | "convenience_fee" | "credit" | "credit_note" | "estimate" | "invoice",
    amount: number,
    invoice?: number,
    credit_note?: number,
    estimate?: number,
    credit_note_netsuite_id?: null | number,
    credit_note_number?: string,
    invoice_number?: string,
}

type Charge = {
    gateway: string,
    payment_source: string,
    checknum: string,
}

export type PaymentsScriptletInputParameters = RestletInputParameters & {
    voided: number,
    date: number,
    currency: string,
    method: string,
    charge: Charge | null,
    amount: number,
    applied: InvoicedPaymentApplication[],
    reference: string | null,
}

//netsuite id => invoiced id
export type Applications = {
    [key: string]: {
        invoicedId: null | number,
        amount: number,
    },
}


/**
 * @NApiVersion 2.x
 * @NScriptType Restlet
 * @NModuleScope Public
 */
define([
    'N/record',
    'N/search',
    'N/log',
    'N/transaction',
    'tmp/src/Models/List',
    'tmp/src/PaymentDeposit',
    'tmp/src/Facades/InvoiceFacade',
    'tmp/src/Facades/CreditNoteFacade',
    'tmp/src/Utilities/Config',
    'tmp/src/Utilities/DateUtilities',
    'tmp/src/Global',
    'tmp/src/Libs/SavePaymentFactory',
    'tmp/src/Utilities/SubsidiaryKey',
], function (record,
             search,
             log,
             transaction,
             List,
             PaymentDeposit,
             InvoiceFacade,
             CreditNoteFacade,
             Config,
             DateUtilities,
             global,
             SavePaymentFactory,
             SubsidiaryKey) {

    class PaymentsModelScriptlet {

        private static voidPayment(paymentId: number): void {
            try {
                transaction.void({
                    type: transaction.Type.CUSTOMER_PAYMENT,
                    id: paymentId,
                });
                log.debug('Payment voided', paymentId);
            } catch (e) {
                const message = 'Reversal voiding feature is enabled, skipping. Disable it in NS -> Go to Setup -> Accounting -> Preferences -> Accounting Preferences';
                log.error(paymentId.toString(), e);
                throw message + String(e);
            }
        }


        private static findInvoiceByNumber(numbers: Record<string, null | number>, nSCustomerId: number): Record<number, number> {
            return PaymentsModelScriptlet.findTransactionByNumber(numbers, nSCustomerId, record.Type.INVOICE, InvoiceFacade);
        }

        private static findCreditNoteByNumber(numbers: Record<string, null | number>, nSCustomerId: number): Record<number, number> {
            return PaymentsModelScriptlet.findTransactionByNumber(numbers, nSCustomerId, record.Type.CREDIT_MEMO, CreditNoteFacade);
        }

        private static findTransactionByNumber(numbers: Record<string, null | number>, nSCustomerId: number, type: NRecord.Type, facade: FacadeInterface): Record<number, number> {
            log.debug("number", numbers);
            const numberArray: any[] = [];
            for (const i in numbers) {
                numberArray.push(['tranid', search.Operator.IS, i]);
                numberArray.push('OR');
            }
            if (!numberArray.length) {
                return {};
            }
            numberArray.pop();
            const filters = [
                numberArray,
                'and',
                ['customer.internalId', search.Operator.IS, nSCustomerId],
                'and',
                ['mainline', search.Operator.IS, 'T'],
            ];
            log.debug('Searching for transaction (filters)', filters);

            const result: Record<string, number> = {};

            search.create({
                type: type.toString(),
                filters: filters,
                columns: ['number', 'subsidiary'],
            }).run().each((item) => {
                log.debug('Invoice match found', item);
                if (!item || !item.id) {
                    return true;
                }
                const id = item.id;
                const number: string = item.getValue('number').toString();
                const invoicedId = numbers[number];
                if (!number || !invoicedId) {
                    return true;
                }
                result[invoicedId] = parseInt(id);
                //reverse map
                if (numbers[number]) {
                    const divisionKey = SubsidiaryKey.get(parseInt(item.getValue('subsidiary').toString()));
                    facade.patch(divisionKey, {
                        accounting_system: 'netsuite',
                        accounting_id: id,
                        number: number,
                    });
                }
                return true;
            });
            return result;
        }


        private static mapInvoices(applied: InvoicedPaymentApplication[], mappings: Record<number, number>): Applications {
            const mapped: Applications = {};

            for (const key in applied) {
                const currentValue = applied[key];
                log.debug('Invoice currentValue', currentValue);
                if (!currentValue || !currentValue.invoice) {
                    continue;
                }
                const invoiceNetSuiteId = currentValue.invoice_netsuite_id || mappings[currentValue.invoice];
                if (!invoiceNetSuiteId) {
                    continue;
                }
                mapped[invoiceNetSuiteId] = {
                    invoicedId: currentValue.invoice,
                    amount: currentValue.amount,
                };
            }
            log.debug('Invoice mapping', mapped);
            return mapped;
        }

        private applyCreditNotes(
            appliedItems: InvoicedPaymentApplication[],
            payment: NRecord.Record | null,
            mappingsInvoices: Record<number, number>,
            mappingsCreditNotes: Record<number, number>,
        ): void {
            for (const kwy in appliedItems) {
                const el = appliedItems[kwy];
                if (!el) {
                    continue;
                }
                const creditNoteId = el.credit_note;
                const invoiceId = el.invoice;
                if (!creditNoteId || !invoiceId) {
                    continue;
                }
                const creditNoteNetSuiteId = el.credit_note_netsuite_id || mappingsCreditNotes[creditNoteId];
                const invoiceNetSuiteId = el.invoice_netsuite_id || mappingsInvoices[invoiceId];
                if (!creditNoteNetSuiteId || !invoiceNetSuiteId) {
                    continue;
                }

                const CN = record.load({
                    type: record.Type.CREDIT_MEMO,
                    id: creditNoteNetSuiteId,
                    isDynamic: true,
                });

                const appliedVal = (CN.getValue('applied') || 0) as string;
                let applied = parseFloat(appliedVal);
                List.getInstance(CN, 'apply').map(function (list2) {
                    const nsInvoiceId = list2.get('doc');
                    if ( invoiceNetSuiteId != nsInvoiceId) {
                        return true;
                    }
                    applied += el.amount || 0;
                    list2.set('total', el.amount);
                    list2.set('amount', el.amount);
                    list2.set('apply', true);
                    if (payment) {
                        list2.set('pymt', payment.id);
                    }
                    list2.commitLine();
                    return false;
                });
                log.debug('CN applied', applied);
                if (applied) {
                    const total = CN.getValue('total') as number;
                    CN.setValue('unapplied', total - applied);
                    CN.setValue('applied', applied);
                    log.debug('CN', CN);
                    CN.save();
                }
            }
        }

        private getAppliedCredits(params: PaymentsScriptletInputParameters): number {
            let credit: number = 0;
            params.applied
                .filter(item => item.type === 'credit')
                .map(item => {
                    credit += item.amount;
                    return true;
                });

            return credit;
        }

        private applyPayment(
            payment: NRecord.Record,
            params: PaymentsScriptletInputParameters,
            invoices: Applications,
            createdDate: Date,
        ): NRecord.Record | null {
            let unapplied = this.getAppliedCredits(params);
            const invoicesToApply = params.applied.filter(item => item.type === 'invoice');
            let applied = 0;
            if (invoicesToApply.length) {
                log.debug('invoicesToApply', invoicesToApply);
                List.getInstance(payment, 'apply').map(function (list2) {
                    let listId = list2.getValueNumber('doc');
                    if (!listId) {
                        return true;
                    }
                    if (invoices[listId] === undefined) {
                        list2.set('apply', false);
                    } else {
                        // @ts-ignore
                        let amount = invoices[listId].amount;
                        applied += amount || 0;
                        list2.set('amount', amount);
                        list2.set('apply', true);
                    }
                    list2.commitLine();
                    return true;
                });
                log.debug('Applied', applied);
            }
            if (!applied && !unapplied) {
                return null;
            }

            if (!payment) {
                throw "Could not create payment";
            }
            const account = PaymentDeposit.fetch(params.currency, params.method);
            log.debug('Matching account', account);
            if (account != null) {
                if (account.custrecord_invd_undeposited_funds) {
                    log.debug('Enabling undeposited funds', account);
                    payment.setValue({
                        fieldId: 'undepfunds',
                        value: 'T',
                        ignoreFieldChange: true,
                    });
                } else {
                    payment.setValue({
                        fieldId: 'account',
                        value: account.custrecord_ivnd_deposit_bank_account,
                        ignoreFieldChange: true,
                    });
                    payment.setValue({
                        fieldId: 'undepfunds',
                        value: 'F',
                        ignoreFieldChange: true,
                    });
                }
            }


            payment.setValue({
                fieldId: 'autoapply',
                value: false,
                ignoreFieldChange: true,
            });
            payment.setValue({
                fieldId: global.INVOICED_RESTRICT_SYNC_TRANSACTION_BODY_FIELD,
                value: true,
                ignoreFieldChange: true,
            });
            payment.setValue({
                fieldId: 'trandate',
                value: createdDate,
                ignoreFieldChange: true,
            });
            /**** CALCULATIONS ****/
            payment.setValue({
                fieldId: 'total',
                value: applied + unapplied,
                ignoreFieldChange: true,
            });
            payment.setValue({
                fieldId: 'payment',
                value: applied + unapplied,
                ignoreFieldChange: true,
            });
            payment.setValue({
                fieldId: 'applied',
                value: applied,
                ignoreFieldChange: true,
            });
            payment.setValue({
                fieldId: 'unapplied',
                value: unapplied,
                ignoreFieldChange: true,
            });
            /**** CALCULATIONS END ****/
            payment.setValue({
                fieldId: 'currencysymbol',
                value: params.currency.toUpperCase(),
                ignoreFieldChange: true,
            });
            if (params.charge) {
                payment.setValue({
                    fieldId: 'memo',
                    value: params.charge.gateway + ': ' + params.charge.payment_source,
                    ignoreFieldChange: true,
                });
            }
            if (params.reference) {
                payment.setValue({
                    fieldId: 'checknum',
                    value: params.reference,
                    ignoreFieldChange: true,
                });
            }


            payment.save({
                enableSourcing: true,
                ignoreMandatoryFields: true,
            });
            return payment;
        }

        public doPost(input: PaymentsScriptletInputParameters) {
            log.debug('Called from POST', input);
            const cfg = Config.getInstance();
            if (!cfg.doSyncPaymentRead()) {
                log.debug('Read sync disabled', undefined);
                return;
            }

            const createdDate = DateUtilities.getCompanyDate(input.date);
            if (input.voided) {
                if (!input.netsuite_id) {
                    log.debug("No payment id specified for void operation", undefined);
                    return;
                }
                PaymentsModelScriptlet.voidPayment(input.netsuite_id);
                return;
            }

            let savePayment: SavePaymentInterface;
            try {
                savePayment = SavePaymentFactory.create(cfg, createdDate, input);
            } catch {
                return;
            }

            let applied = 0;
            const invoiceMappings: Record<string, null | number> = {};
            const creditNoteMappings: Record<string, null | number> = {};

            for (const key in input.applied) {
                const currentValue = input.applied[key];
                log.debug('Invoice currentValue', currentValue);
                if (!currentValue || !currentValue.invoice) {
                    continue;
                }
                if (!currentValue.invoice_netsuite_id && currentValue.invoice_number) {
                    invoiceMappings[currentValue.invoice_number] = currentValue.invoice;
                }
                if (!currentValue.credit_note_netsuite_id && currentValue.credit_note_number) {
                    creditNoteMappings[currentValue.credit_note_number] = currentValue.credit_note ?? null;
                }
            }

            const mappedInvoices = PaymentsModelScriptlet.findInvoiceByNumber(invoiceMappings, savePayment.customer);
            const mappedCreditNotes = PaymentsModelScriptlet.findCreditNoteByNumber(creditNoteMappings, savePayment.customer);


            const invoices: Applications = PaymentsModelScriptlet.mapInvoices(input.applied, mappedInvoices);
            log.debug("applicationItems", invoices);
            if (Object.keys(invoices).length === 0 && applied === 0) {
                log.audit("Payment was not synced: Empty applications list", invoices);
                return;
            }
            const convenienceFeeInvoice = savePayment.applyConvenienceFee(input, invoices);
            log.debug("convenienceFeeInvoice", convenienceFeeInvoice);
            //we have convenience fee
            if (convenienceFeeInvoice) {
                invoices[convenienceFeeInvoice.id] = {
                    invoicedId: null,
                    amount: convenienceFeeInvoice.amount,
                };
            }
            log.debug('Invoices', invoices);

            let payment = savePayment.payment;
            try {
                payment = this.applyPayment(payment, input, invoices, createdDate) || payment;
            } catch (e) {
                PaymentsModelScriptlet.cleanConvenienceFee(convenienceFeeInvoice);
                throw e;
            }

            try {
                this.applyCreditNotes(input.applied, payment, mappedInvoices, mappedCreditNotes);
            } catch (e) {
                log.debug('Error on CN application', e);
                if (payment) {
                    if (payment.id) {
                        record.delete({
                            id: payment.id,
                            type: record.Type.CUSTOMER_PAYMENT,
                        });
                    }
                    PaymentsModelScriptlet.cleanConvenienceFee(convenienceFeeInvoice);
                }
                throw e;
            }

            return payment;
        }

        private static cleanConvenienceFee(convenienceFeeInvoice: ConvenienceFeeInvoice | null): void {
            if (convenienceFeeInvoice) {
                if (convenienceFeeInvoice.new) {
                    if (convenienceFeeInvoice.invoice.id) {
                        record.delete({
                            id: convenienceFeeInvoice.invoice.id,
                            type: record.Type.INVOICE,
                        });
                    }
                } else {
                    convenienceFeeInvoice.invoice.setValue('amount', convenienceFeeInvoice.oldAmount);
                    convenienceFeeInvoice.invoice.save({
                        enableSourcing: true,
                        ignoreMandatoryFields: true,
                    });
                }
            }
        }
    }


    return {
        //get:
        post: (params: PaymentsScriptletInputParameters) => (new PaymentsModelScriptlet()).doPost(params),
        put: (params: PaymentsScriptletInputParameters) => (new PaymentsModelScriptlet()).doPost(params),
        //delete:
    };
});