import {Row} from "./Row";
import {NetSuiteObjectInterface} from "./NetSuiteObjectInterface";
import {ContextExtendedInterface, ContextInterface} from "./Context";

export interface lookUpResult {
    [key: string]: string,
}

type JSONResponse = {
  id: number,
};


export interface NetSuiteTypeSimplifiedInterface extends NetSuiteObjectInterface {
    buildRow(): Row | null;
    getId(): number;
    shouldSync(): boolean;
}

export interface NetSuiteTypeInterface extends NetSuiteTypeSimplifiedInterface {
    send(): JSONResponse | null;
    getLastModifiedDate(): null | Date;
    getData(): ContextInterface;
}

export interface NetSuiteTypeInstanceFactoryInterface {
    getInstance(context: ContextInterface): NetSuiteTypeInterface;
}