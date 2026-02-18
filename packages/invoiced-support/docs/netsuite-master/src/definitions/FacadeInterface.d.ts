import {CustomerOwnerRecordRow} from "./Row";
import {NetSuiteTypeInterface} from "./NetSuiteTypeInterface";

export interface FacadeInterface {
    patch(key: null | string, data: CustomerOwnerRecordRow): null | object;
}
