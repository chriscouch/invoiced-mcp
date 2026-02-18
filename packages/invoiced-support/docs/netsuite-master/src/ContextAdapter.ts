import * as NRecord from "N/record";
import * as Log from "N/log";
import {
    ContextExtendedDynamicInterface,
    ContextExtendedInterface,
    ContextFactoryInterface,
    ContextInterface,
    CustomerOwnedRecordContextObject
} from "./definitions/Context";

type defineCallback = (
    log: typeof Log,
    record: typeof NRecord,
) => ContextFactoryInterface;

declare function define(arg: string[], fn: defineCallback): void;


/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define(['N/log', 'N/record'], function (log, record) {
    abstract class Context implements ContextInterface  {
        public getValueBoolean(fieldId: string): boolean {
            const val = this.getValue(fieldId);
            return val === true || val === 'T' || val == 1;
        }
        public getValueString(fieldId: string): string {
            return this.getValue(fieldId).toString();
        }

        public abstract getValue(key: string): any;
        public abstract getId(): number;

        abstract getType(): NRecord.Type | string;
    }

    class ContextPlain extends Context {
        protected context: Record<string, any>;

        constructor(context: CustomerOwnedRecordContextObject) {
            super();
            this.context = context;
        }

        protected setValue(key: string, value: any): void {
            this.context[key] = value;
        }


        public getValue(key: string): any {
            return this.context[key];
        }
        public getId(): number {
            return this.context["id"];
        }

        public getType(): NRecord.Type | string {
            return this.context["type"];
        }
    }

    class ContextRecord extends Context implements ContextExtendedInterface {
        public context: NRecord.Record;

        constructor(context: NRecord.Record) {
            super();
            this.context = context;
        }

        public getValue(key: string): any {
            return this.context.getValue(key);
        }
        public getId(): number {
            return this.context.id;
        }

        public setValue(key: string, value: NRecord.FieldValue): void {
            this.context.setValue(key, value);
        }

        public getType(): NRecord.Type | string {
            return this.context.type;
        }
    }

    class ContextDynamicRecord extends ContextRecord implements ContextExtendedDynamicInterface {
        public setValue(key: string, value: NRecord.FieldValue): void {
            this.context.setValue(key, value);
        }
    }

    return {
        getInstanceSimple: function (context: CustomerOwnedRecordContextObject) {
            log.debug('Context', context);
            return new ContextPlain(context);
        },
        getInstanceExtended: function (context: NRecord.Record) {
            log.debug('Context', context);
            return new ContextRecord(context);
        },
        getInstanceDynamicExtended: function (type: NRecord.Type, id: number) {
            try {
                const rec = record.load({
                    type: type,
                    id: id,
                    isDynamic: true,
                });
                log.debug('Context', rec);

                return new ContextDynamicRecord(rec);
            } catch (e: any) {
                if (e.toString().indexOf("The record you are attempting to load has a different type: job from the type specified: customer.") !== -1) {
                    log.audit('Error job loading record, skipping to be synced with the projects', e);

                    return null;
                }

                throw e;
            }
        },
    };
});
