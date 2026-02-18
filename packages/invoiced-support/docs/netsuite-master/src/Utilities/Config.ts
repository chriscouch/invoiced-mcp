import * as NConfig from "N/config";
import {FieldValue, Record} from 'N/record';
import * as Nlog from 'N/log';

type defineCallback = (
    config: typeof NConfig,
    log: typeof Nlog
) => ConfigFactory;

declare function define(arg: string[], fn: defineCallback): void;

export type ConfigParameters = {
    custscript_invd_sync_customers?: boolean,
    custscript_invd_sync_projects?: boolean,
    custscript_invd_sync_invoices?: boolean,
    custscript_invd_sync_invoices_as_drafts?: boolean,
    custscript_invd_sync_credit_notes?: boolean,
    custscript_invd_read_payments?: boolean,
    custscript_invd_sync_payments?: boolean,
    custscript_invd_convenience_fee?: number,
    custscript_invd_convenience_fee_tax_code?: number,
    custscript_invd_api_key?: string,
    custscript_invd_is_sandbox?: boolean,
    custscript_invd_item_dates?: boolean,
    custscript_invd_start_date?: Date | null,
    custscript_invd_send_pdf?: boolean,
    custscript_invd_dst_observed?: boolean,
    custscript_invd_rts?: boolean,
    custscript_invd_ss?: boolean,
    custscript_invd_customer_id_cursor?: number,
    custscript_invd_invoice_id_cursor?: number,
    custscript_invd_cn_id_cursor?: number,
    custscript_invd_payment_id_cursor?: number,
    custscript_invd_project_id_cursor?: number,
    custscript_invd_customer_cursor?: Date,
    custscript_invd_project_cursor?: Date,
    custscript_invd_invoice_cursor?: Date,
    custscript_invd_cn_cursor?: Date,
    custscript_invd_payment_cursor?: Date,
}

export interface ConfigInterface {
    getStartDate(): Date | undefined | null;
    getCustomerCursor(): Date;
    getProjectCursor(): Date;
    getInvoiceCursor(): Date;
    getCreditNoteCursor(): Date;
    getPaymentCursor(): Date;
    setCustomerCursor(date: Date): void;
    setProjectCursor(date: Date): void;
    setInvoiceCursor(date: Date): void;
    setCreditNoteCursor(date: Date): void;
    setPaymentCursor(date: Date): void;
    doSendPDF(): boolean;

    getCustomerIdCursor(): number;
    getProjectIdCursor(): number;
    getInvoiceIdCursor(): number;
    getCreditNoteIdCursor(): number;
    getPaymentIdCursor(): number;
    getProjectIdCursor(): number;
    setCustomerIdCursor(id: number): void;
    setProjectIdCursor(id: number): void;
    setInvoiceIdCursor(id: number): void;
    setCreditNoteIdCursor(id: number): void;
    setPaymentIdCursor(id: number): void;
    setProjectIdCursor(id: number): void;

    getApiKey(): string;
    isStaging(): boolean;
    isSandbox(): boolean;
    syncLineItemDates(): boolean;
    getConvenienceFee(): FieldValue;
    getConvenienceFeeTaxCode(): FieldValue;
    doSyncCustomers(): boolean;
    doSyncProjects(): boolean;
    doSyncInvoices(): boolean;
    doSyncInvoiceDrafts(): boolean;
    doSyncCreditNotes(): boolean;
    doSyncPaymentRead(): boolean;
    doSyncPaymentWrite(): boolean;
    getCursorError(): string;
    getStartDateError(): string;

    doRealTimeSync(): boolean;
    doScheduledSync(): boolean;

    getDateFormat(): string,
    getTimeFormat(): string,
    set(parameters: ConfigParameters): void;

    isDST(): boolean;
}

export interface ConfigInformationInterface {
    getTimeZone(): string,
}

export interface ConfigFactory {
    getInstance(): ConfigInterface;
    getInformation(): ConfigInformationInterface;
    dateFromNSString(date: string): Date | null;
    getCompanyDate(time: number, reverse?: boolean): Date;
}

/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define([
    'N/config',
    'N/log',
], function(config, log) {
    let instance: Config;
    let information: ConfigInformation;

    class ConfigInformation implements ConfigInformationInterface
    {
        private readonly cfg: Record;

        constructor() {
            this.cfg = config.load({
                type: config.Type.COMPANY_INFORMATION,
            });
            log.audit('Config information loaded', this.cfg);
        }

        getTimeZone(): string {
            return String(this.cfg.getText({fieldId: 'timezone'}));
        }

    }

    class Config implements ConfigInterface {
        private readonly cfg: Record;

        constructor() {
            this.cfg = config.load({
                type: config.Type.COMPANY_PREFERENCES,
            });
            log.audit('Config loaded', this.cfg);
        }

        doSyncCustomers(): boolean {
            return this.getValueBoolean('custscript_invd_sync_customers');
        }
        doSyncProjects(): boolean {
            return this.getValueBoolean('custscript_invd_sync_projects');
        }
        doSyncInvoices(): boolean {
            return this.getValueBoolean('custscript_invd_sync_invoices');
        }
        doSyncInvoiceDrafts(): boolean {
            return this.getValueBoolean('custscript_invd_sync_invoices_as_drafts');
        }
        doSyncCreditNotes(): boolean {
            return this.getValueBoolean('custscript_invd_sync_credit_notes');
        }
        doSyncPaymentRead(): boolean {
            return this.getValueBoolean('custscript_invd_read_payments');
        }
        doSyncPaymentWrite(): boolean {
            return this.getValueBoolean('custscript_invd_sync_payments');
        }

        getConvenienceFee(): FieldValue {
            return this.cfg.getValue('custscript_invd_convenience_fee');
        }
        getConvenienceFeeTaxCode(): FieldValue {
            return this.cfg.getValue('custscript_invd_convenience_fee_tax_code');
        }

        private getValueBoolean(fieldId: string): boolean {
            const val = this.cfg.getValue(fieldId);
            return val === true || val === 'T' || val == 1;
        }

        public getApiKey(): string {
            return this.cfg.getValue('custscript_invd_api_key') as string;
        }

        public isStaging(): boolean {
            return this.getValueBoolean('custscript_invd_is_staging');
        }
        public isSandbox(): boolean {
            return this.getValueBoolean('custscript_invd_is_sandbox');
        }
        public syncLineItemDates(): boolean {
            return this.getValueBoolean('custscript_invd_item_dates');
        }

        public getTimeFormat(): string {
            return String(this.cfg.getValue({fieldId: 'TIMEFORMAT'}));
        }

        public getDateFormat(): string {
            return String(this.cfg.getValue({fieldId: 'DATEFORMAT'}));
        }

        private getCursor(value: string): Date {
            let date = this.cfg.getValue(value);
            if (!date) {
                return new Date();
            }
            if (typeof date === "string") {
                let dateInitial = date;
                date = Config.dateFromNSString(date);
                if (!date) {
                    log.error("Current cursor", dateInitial);
                    throw this.getCursorError();
                }
            }
            return date as Date;
        }

        public getCustomerCursor(): Date {
            return this.getCursor("custscript_invd_customer_cursor");
        }
        public getProjectCursor(): Date {
            return this.getCursor("custscript_invd_project_cursor");
        }
        public getInvoiceCursor(): Date {
            return this.getCursor("custscript_invd_invoice_cursor");
        }
        public getCreditNoteCursor(): Date {
            return this.getCursor("custscript_invd_cn_cursor");
        }
        public getPaymentCursor(): Date {
            return this.getCursor("custscript_invd_payment_cursor");
        }

        public setCustomerCursor(date: Date): void {
            this.cfg.setValue('custscript_invd_customer_cursor', date);
            this.cfg.save();
        }

        public setProjectCursor(date: Date): void {
            this.cfg.setValue('custscript_invd_project_cursor', date);
            this.cfg.save();
        }

        public setInvoiceCursor(date: Date): void {
            this.cfg.setValue('custscript_invd_invoice_cursor', date);
            this.cfg.save();
        }
        public setCreditNoteCursor(date: Date): void {
            this.cfg.setValue('custscript_invd_cn_cursor', date);
            this.cfg.save();
        }
        public setPaymentCursor(date: Date): void {
            this.cfg.setValue('custscript_invd_payment_cursor', date);
            this.cfg.save();
        }

        public getStartDate(): Date | undefined | null {
            let date = this.cfg.getValue('custscript_invd_start_date');
            if (date && typeof date === "string") {
                let dateInitial = date;
                date = Config.dateFromNSString(date);
                if (!date) {
                    log.error("Current start date", dateInitial);
                    throw this.getStartDateError();
                }
            }
            return date as Date | undefined | null;
        }

        public getCursorError(): string {
            return "Your cursor is set to string, please update the script start date";
        }

        public getStartDateError(): string {
            return "Your start date is set to string, please update the script cursors";
        }

        public doSendPDF(): boolean {
            const val = this.cfg.getValue('custscript_invd_send_pdf')
            return val === true || val === 'T' || val == 1;
        }

        isDST(): boolean {
            const val = this.cfg.getValue('custscript_invd_dst_observed')
            return val === true || val === 'T' || val == 1;
        }

        set(parameters: ConfigParameters): void {
            for (const i in parameters) {
                // @ts-ignore
                this.cfg.setValue(i, parameters[i]);
            }
            this.cfg.save();
        }

        getCreditNoteIdCursor(): number {
            return this.cfg.getValue('custscript_invd_cn_id_cursor') as number;
        }

        getCustomerIdCursor(): number {
            return this.cfg.getValue('custscript_invd_customer_id_cursor') as number;
        }

        getInvoiceIdCursor(): number {
            return this.cfg.getValue('custscript_invd_invoice_id_cursor') as number;
        }

        getPaymentIdCursor(): number {
            return this.cfg.getValue('custscript_invd_payment_id_cursor') as number;
        }

        getProjectIdCursor(): number {
            return this.cfg.getValue('custscript_invd_payment_id_cursor') as number;
        }

        doRealTimeSync(): boolean {
            return this.getValueBoolean('custscript_invd_rts') as boolean;
        }

        doScheduledSync(): boolean {
            return this.getValueBoolean('custscript_invd_ss') as boolean;
        }

        setCreditNoteIdCursor(id: number): void {
            this.cfg.setValue('custscript_invd_cn_id_cursor', id);
            this.cfg.save();
        }

        setCustomerIdCursor(id: number): void {
            this.cfg.setValue('custscript_invd_customer_id_cursor', id);
            this.cfg.save();
        }

        setInvoiceIdCursor(id: number): void {
            this.cfg.setValue('custscript_invd_invoice_id_cursor', id);
            this.cfg.save();
        }

        setPaymentIdCursor(id: number): void {
            this.cfg.setValue('custscript_invd_payment_id_cursor', id);
            this.cfg.save();
        }

        setProjectIdCursor(id: number): void {
            this.cfg.setValue('custscript_invd_project_id_cursor', id);
            this.cfg.save();
        }

        public static dateFromNSString(date: string): Date | null {
            log.debug('Determine date from', date);
            //inconsistency of D/M/Y and M/D/Y
            const americanFormatRegxep = new RegExp("^([0-9]{1,2})/([0-9]{1,2})/([0-9]{4})");
            const matches = date.match(americanFormatRegxep);
            if (matches) {
                if (parseInt(matches[1] as string) > 12) {
                    date = date.replace(americanFormatRegxep, "$3-$2-$1");
                } else if (parseInt(matches[2] as string) > 12) {
                    date = date.replace(americanFormatRegxep, "$3-$1-$2");
                } else {
                    return null;
                }
            }

            const stringMonth: string[] | null = date.match(/[a-z]{3,10}/i);
            if (stringMonth) {
                const numeric = 'January___February__March_____April_____May_______June______July______August____September_October___November__December__'.indexOf(stringMonth[0] as string) / 10 + 1;
                const numericString = ("0" + numeric).slice (-2);
                date = date.replace(/[a-z]{3,10}/i, numericString);
            }
            //make all starting days double-digit
            date = date.replace(/^([1-9])[-. ]/, "0$1-");
            //cleaning non - separators
            date = date.replace(", ", "-").
            replace(/[\/.]/g, "-");

            //minor change for very specific use case
            date = date.replace(/^(\d{2}) /, "$1-");

            //arrange data properly
            date = date.replace(/^(\d{1,2})-(\d{1,2})-(\d{4})/, "$3-$2-$1");

            //replace last dates to zero prefixed
            date = date.replace(/^(\d{4})-(\d)-/, "$1-0$2-")
                .replace(/^(\d{4})-(\d{2})-(\d) /, "$1-$2-0$3 ");

            //normalize time part
            //12 PM/AM edge cases
            date = date.replace(/12([-:\d]+) am$/i, "00$1");
            date = date.replace(/12([-:\d]+) pm$/i, "12$1");

            //All other cases
            date = date.replace(/ am$/i, "");
            const hourMatch = date.match(/(\d)[-:\d]+ pm$/i);
            if (hourMatch) {
                const hour = parseInt(hourMatch[1] as string) + 12;
                date = date.replace(/\d([-:\d]+) pm$/i,hour + "$1");
            }
            //netsuite doesn't work with data parse
            date = date.replace(/[:\s]/g, "-");

            log.debug('Determine dated', date);

            const dateArray: number[] = date.split("-").map((item) => parseInt(item));
            const newDate = Date.UTC(
                dateArray[0] as number,
                (dateArray[1] as number) - 1,
                dateArray[2],
                dateArray[3],
                dateArray[4],
                dateArray[5]) || null;
            if (!newDate) {
                return null;
            }

            return Config.getCompanyDate(newDate / 1000);
        };

        public static getCompanyDate(time: number, reverse?: boolean): Date {
            if (!instance) {
                instance = new Config();
            }
            if (!information) {
                information = new ConfigInformation();
            }

            const companyTimeZone = information.getTimeZone();
            const timeZoneOffSet = (companyTimeZone.indexOf('(GMT)') == 0) ? 0 : Number(companyTimeZone.substr(4, 6).replace(/\+|:00/gi, '').replace(/:30/gi, '.5'));
            log.debug("companyTimeZone, timeZoneOffSet", [companyTimeZone, timeZoneOffSet]);

            let hourDSTOffset = 0;
            if (instance.isDST()) {
                const d = new Date(time * 1000);
                const jan = new Date(d.getFullYear(), 0, 1);
                const jul = new Date(d.getFullYear(), 6, 1);
                const stdOffset = Math.max(jan.getTimezoneOffset(), jul.getTimezoneOffset());
                hourDSTOffset = (d.getTimezoneOffset() < stdOffset ? 1 : 0) * 3600;
            }

            //NetSuite has weird behavior, when it applies payment date in UTC format as company timezone format
            //so in, if you will send to NS 01-01-2022 12AM it will apply -6 timezone to it on setValue function
            //to fix it - we need to reverse company timezone value from the payment first.
            const offsetMultiplier = reverse ? -1 : 1;
            const companyDateTime = (time - offsetMultiplier * timeZoneOffSet * 3600 + hourDSTOffset) * 1000;
            return new Date(companyDateTime);
        };

    }

    return {
        getInstance: function() {
            if (!instance) {
                instance = new Config();
            }
            return instance;
        },
        getInformation: function() {
            if (!information) {
                information = new ConfigInformation();
            }
            return information;
        },
        //should be here instead of date utilities because of circular dependencies
        dateFromNSString(date: string): Date | null {
            return Config.dateFromNSString(date);
        },
        getCompanyDate(time: number, reverse?: boolean): Date {
            return Config.getCompanyDate(time, reverse);
        }
    };
});