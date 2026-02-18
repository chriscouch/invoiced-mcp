import {Operator} from "N/search";
import {FieldValue} from "N/record";

type defineCallback = () => object;

declare function define(arg: string[], fn: defineCallback): void;


export interface SearchCriteriaBuilderFactory {
    getInstance(): SearchCriteriaBuilderInterface;
}
type SearchOperator = "OR" | "AND";
type CriteriaArray = [string, Operator, FieldValue];
export type SearchCriteria = CriteriaArray|SearchOperator|SearchCriteria[];

export interface SearchCriteriaBuilderInterface {
    set(item: SearchCriteria[]): SearchCriteriaBuilderInterface;
    and(item: CriteriaArray): SearchCriteriaBuilderInterface;
    or(item: CriteriaArray): SearchCriteriaBuilderInterface;
    get(): SearchCriteria[];
}

/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define([], function () {

    class SearchCriteriaBuilder implements SearchCriteriaBuilderInterface {
        private stack: SearchCriteria[] = [];

        private add(item: CriteriaArray, operator: SearchOperator): void {
            if (this.stack.length > 0) {
                this.stack.push(operator);
            }
            this.stack.push(item);
        }

        public set(item: SearchCriteria[]): SearchCriteriaBuilder {
            this.stack = item;
            return this;
        }

        public and(item: CriteriaArray): SearchCriteriaBuilder {
            this.add(item, "AND");
            return this;
        }
        public or(item: CriteriaArray): SearchCriteriaBuilder {
            this.add(item, "OR");
            return this;
        }

        public get(): SearchCriteria[] {
            return this.stack;
        }
    }
    /**
     * Address convertor class
     */
    return {
        // mapping: mapping,
        getInstance: (): SearchCriteriaBuilderInterface => {
            return new SearchCriteriaBuilder();
        }
    };
});
