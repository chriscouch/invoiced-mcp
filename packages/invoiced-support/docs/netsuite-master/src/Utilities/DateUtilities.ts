import {ConfigFactory} from "./Config";

export interface DateUtilitiesInterface {
    getCompanyDate(time: number, reverse?: boolean): Date;
    getFormat(dateOnly: boolean): string;
}

type defineCallback = (configFactory: ConfigFactory) => DateUtilitiesInterface;

declare function define(arg: string[], fn: defineCallback): void;
/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
define(['tmp/src/Utilities/Config',], function (ConfigFactory) {

    /**
     * Converts UTC UNIX timestamp to company date
     * Reverse - true - to add the offset
     */
    return {
        getCompanyDate(time: number, reverse?: boolean): Date {
            return ConfigFactory.getCompanyDate(time, reverse);
        },
        getFormat(dateOnly: boolean): string {
            const informationCfg = ConfigFactory.getInstance();
            const dateFormat = informationCfg.getDateFormat();

            if (dateOnly) {
                return dateFormat;
            }
            return dateFormat + ' ' + informationCfg.getTimeFormat();
        },
    };
});
