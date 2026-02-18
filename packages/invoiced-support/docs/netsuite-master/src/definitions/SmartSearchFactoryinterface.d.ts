import {ContextInterface} from "./Context";
import {NetSuiteTypeInterface} from "./NetSuiteTypeInterface";

export interface SmartSearchFactoryInterface {
    getInstance(context: ContextInterface): NetSuiteTypeInterface
}

