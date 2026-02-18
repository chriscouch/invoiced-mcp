import * as NRecord from "N/record";
import * as Log from "N/log";
import {ConvenienceFeeFactoryInterface, ConvenienceFeeInvoice} from "../Models/ConvenienceFee";
import {ConfigInterface} from "../Utilities/Config";
import {Applications, PaymentsScriptletInputParameters} from "../PaymentsModelScriptlet";
import {CustomerMatcherInterface} from "./CustomerMatcher";


type defineCallback = (
    record: typeof NRecord,
    log: typeof Log,
    ConvenienceFee: ConvenienceFeeFactoryInterface,
    CustomerMatcher: CustomerMatcherInterface) => SavePaymentFactoryInterface;

declare function define(arg: string[], fn: defineCallback): void;

export interface SavePaymentFactoryInterface {
    create(
        cfg: ConfigInterface,
        createdDate: Date,
        input: PaymentsScriptletInputParameters
    ): SavePaymentInterface;
}

export interface SavePaymentInterface {
    payment: NRecord.Record;
    customer: number;
    applyConvenienceFee(params: PaymentsScriptletInputParameters, invoices: Applications): null | ConvenienceFeeInvoice;
}



/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define([
    'N/record',
    'N/log',
    'tmp/src/Models/ConvenienceFee',
    'tmp/src/Libs/CustomerMatcher',
], function (record,
             log,
             ConvenienceFee,
             CustomerMatcher) {
    abstract class AbstractPayment implements SavePaymentInterface {
        protected constructor(
            public payment: NRecord.Record,
            public customer: number,
            ) {
        }

        public applyConvenienceFee(params: PaymentsScriptletInputParameters, invoices: Applications): null | ConvenienceFeeInvoice {
            const convenienceFeeObject = ConvenienceFee.getInstance(params.applied);
            if (!convenienceFeeObject) {
                return null;
            }

            const feeInvoice = convenienceFeeObject.findExistingConvenienceFee(this.payment);
            if (feeInvoice) {
                return feeInvoice;
            }

            const firstInvoiceId = Object.keys(invoices)[0];
            if (!firstInvoiceId) {
                return null;
            }
            const existingInvoice = record.load({
                type: record.Type.INVOICE,
                id: firstInvoiceId,
                isDynamic: true,
            });

            const invoice = convenienceFeeObject.createConvenienceFeeInvoice(this.payment, existingInvoice);

            //this should be called,
            //because at this point NetSuite pre cashes invoices available for the payment
            if (invoice) {
                this.reloadApplications(invoice.id);
            }

            return invoice;
        }

        protected abstract reloadApplications(invoiceId: undefined|number|null): void;
    }

    class UpdatePayment extends AbstractPayment {
        constructor(input: PaymentsScriptletInputParameters) {
            const payment = record.load({
                type: record.Type.CUSTOMER_PAYMENT,
                id: input.netsuite_id,
                isDynamic: true,
            });

            if (!payment) {
                throw "Customer not found";
            }

            super(payment, payment.getValue('customer') as number);
        }

        protected reloadApplications(): void
        {
        }
    }

    class CreatePayment extends AbstractPayment {
        constructor(cfg: ConfigInterface, createdDate: Date, input: PaymentsScriptletInputParameters) {
            const createdThreshold = cfg.getStartDate();
            if (createdThreshold && (createdThreshold > createdDate)) {
                log.audit('Sync skipped because date created threshold', [createdThreshold, createdDate]);
                throw "Sync skipped because date created threshold";
            }
            let customerId = null;
            if (input.parent_customer) {
                customerId = CustomerMatcher.getCustomerIdDecorated(
                    input.parent_customer.accountnumber,
                    input.parent_customer.companyname,
                    input.parent_customer.netsuite_id,
                );
            }
            if (!customerId) {
                log.audit("Payment was not synced", "Customer not found");
                throw "Customer not found";
            }
            let invoiceId = null;
            if (input.applied.length) {
                for (const i in input.applied) {
                    invoiceId = input.applied[i]?.invoice_netsuite_id;
                    if (invoiceId) {
                        break;
                    }
                }
            }

            const payment = CreatePayment.createPayment(customerId, invoiceId);
            super(payment, customerId);
        }

        private static createPayment(customerId: number, invoiceId: undefined|number|null): NRecord.Record {
            if (!invoiceId) {
                const createdPayment = record.transform({
                    fromType: record.Type.CUSTOMER,
                    fromId: customerId,
                    toType: record.Type.CUSTOMER_PAYMENT,
                    isDynamic: true,
                });
                log.debug('Payment created from customer', createdPayment);

                return createdPayment;
            }
            const invoice = record.load({
                type: record.Type.INVOICE,
                id: invoiceId,
                isDynamic: false,
            })
            log.debug('Source invoice', invoice);

            const createdPayment = record.create({
                type: record.Type.CUSTOMER_PAYMENT,
                isDynamic: true,
            });
            createdPayment.setValue({
                fieldId: 'customer',
                value: customerId
            });
            createdPayment.setValue({
                fieldId: 'currency',
                value: invoice.getValue('currency')
            });
            const subsidiaryId = invoice.getValue('subsidiary');
            log.debug('Source subsidiaryId', subsidiaryId);
            createdPayment.setValue({
                fieldId: 'subsidiary',
                value: subsidiaryId,
            });
            const accountId = invoice.getValue('account');
            log.debug('Source accountId', accountId);
            createdPayment.setValue({
                fieldId: 'aracct',
                value: accountId,
            });
            log.debug('Payment created', createdPayment);
            return createdPayment;
        }


        protected reloadApplications(invoiceId: undefined|number|null): void
        {
            this.payment = CreatePayment.createPayment(this.customer, invoiceId);
        }

    }


    return {
        create: (
            cfg: ConfigInterface,
            createdDate: Date,
            input: PaymentsScriptletInputParameters) => input.netsuite_id
            ? new UpdatePayment(input)
            : new CreatePayment(cfg, createdDate, input),
    };
});