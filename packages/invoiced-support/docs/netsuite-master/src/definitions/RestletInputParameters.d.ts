import {CustomerRestletInputParameters} from "../Libs/SaveCustomerFactory";

export type RestletInputParameters = {
    parent_customer?: null | CustomerRestletInputParameters,
    //invoiced id
    id: number,
    netsuite_id: null | number;
    [key: string]: any;
}