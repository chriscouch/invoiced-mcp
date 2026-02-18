import {FieldValue, Record} from "N/record";
import {listCallback, ListFactoryInterface, ListInterface} from "../definitions/List";
import * as Log from "N/log";
import {InvoicedRowGenericValue} from "../definitions/Row";

type defineCallback = (
    log: typeof Log) => ListFactoryInterface;
declare function define(arg: string[], fn: defineCallback): ListInterface;

/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define(['N/log'], function (log) {
    abstract class List implements ListInterface {
        protected context: Record;
        protected listId: string;
        public length: number;

        constructor(context: Record, listId: string) {
            log.debug('Making list from context', context);
            this.context = context;
            this.listId = listId;
            this.length = this.context.getLineCount({
                sublistId: this.listId,
            });
        }

        public getValueString(fieldId: string): null | string {
            const value = this.get(fieldId);
            return value ? value.toString() : null;
        }

        public getValueNumber(fieldId: string): null | number {
            const value = this.getValueString(fieldId);
            return value ? parseFloat(value) : null;
        }

        public getValueDate(fieldId: string): null | Date {
            return this.get(fieldId) as Date | null;
        }

        public getValueBoolean(fieldId: string): boolean {
            const val = this.get(fieldId);
            return val === true || val === 'T';
        }

        public abstract set(fieldId: string, value: FieldValue, ignoreFieldChange?: boolean): void;

        public abstract getSubRecord(fieldId: string): Record;

        protected abstract iterationCallback(callback: listCallback<ListInterface>): any[];

        public abstract commitLine(): void;

        public map(callback: listCallback<ListInterface>): any[] {
            log.debug('List ' + this.listId + ' contains n items', this.length);
            return this.iterationCallback(callback);
        }

        public get(fieldId: string): InvoicedRowGenericValue {
            const item: FieldValue = this.getItem(fieldId);
            if (item instanceof Date) {
                return item.toString();
            }
            return item;
        }

        public abstract getItem(fieldId: string): FieldValue;
    }

    class DynamicList extends List {
        protected iterationCallback(callback: listCallback<ListInterface>): any[] {
            const result = [];
            for (let i = 0; i < this.length; i++) {
                this.context.selectLine({
                    sublistId: this.listId,
                    line: i,
                });
                let item = callback(this);
                if (item === true) {
                    continue;
                }
                if (item === false) {
                    break;
                }
                result.push(item);
            }
            return result;
        }

        public getItem(fieldId: string): FieldValue {
            return this.context.getCurrentSublistValue({
                sublistId: this.listId,
                fieldId: fieldId,
            });

        }

        public set(fieldId: string, value: FieldValue, ignoreFieldChange: boolean): void {
            if (ignoreFieldChange === undefined) {
                ignoreFieldChange = true;
            }
            this.context.setCurrentSublistValue({
                sublistId: this.listId,
                fieldId: fieldId,
                value: value,
                ignoreFieldChange: ignoreFieldChange,
            });
        }

        public getSubRecord(fieldId: string): Record {
            return this.context.getCurrentSublistSubrecord({
                sublistId: this.listId,
                fieldId: fieldId,
            });
        }

        public commitLine(): void {
            this.context.commitLine({
                sublistId: this.listId,
            });
        }
    }

    class StaticList extends List {
        private position: number = 0;

        iterationCallback(callback: listCallback<ListInterface>): any[] {
            const result = [];
            for (this.position = 0; this.position < this.length; ++this.position) {
                let item = callback(this);
                if (item === true) {
                    continue;
                }
                if (item === false) {
                    break;
                }
                result.push(item);
            }
            return result;
        }

        public getItem(fieldId: string): FieldValue {
            return this.context.getSublistValue({
                sublistId: this.listId,
                fieldId: fieldId,
                line: this.position,
            });
        }

        public set(fieldId: string, value: FieldValue): void {
            this.context.setSublistValue({
                sublistId: this.listId,
                fieldId: fieldId,
                value: value,
                line: this.position,
            });
        }

        public getSubRecord(fieldId: string): Record {
            return this.context.getSublistSubrecord({
                sublistId: this.listId,
                fieldId: fieldId,
                line: this.position,
            });
        }

        public commitLine(): void {
            throw "Line can't be committed in the static record";
        }
    }

    return {
        getInstance: function (context: Record, listId: string) {
            if (context.toString() === 'dynamic record') {
                return new DynamicList(context, listId);
            }
            return new StaticList(context, listId);
        },
    };
});
