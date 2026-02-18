import * as NSearch from "N/search";
import * as Nlog from "N/log";

/**
 *@NApiVersion 2.x
 *@NModuleScope Public
 */

export interface PaymentDepositInterface {
    fetch(currency: string, paymentType: string): null | {
        custrecord_invd_undeposited_funds: string,
        custrecord_ivnd_deposit_bank_account: number,
    };
}


type defineCallback = (
    search: typeof NSearch,
    log: typeof Nlog) => PaymentDepositInterface;

declare function define(arg: string[], fn: defineCallback): PaymentDepositInterface;


define(['N/search', 'N/log'], function (search, log) {
    type AvailableConditions = 'custrecord_invd_currency' | 'custrecord_invd_payment_method';

    type Conditions = {
        [key in AvailableConditions]?: string
    }

    function calculateScore(route: Conditions, rule: Conditions) {
        log.debug('Calculating score for rule', rule);
        let score = 0;

        let tmpScore = getScore(route, rule, 'custrecord_invd_currency');
        if (tmpScore === -1) {
            return -1;
        }
        score += tmpScore;
        log.debug('score', [route['custrecord_invd_currency'], rule['custrecord_invd_payment_method'], score]);
        tmpScore = getScore(route, rule, 'custrecord_invd_payment_method');
        if (tmpScore === -1) {
            return -1;
        }
        score += tmpScore;
        log.debug('score', [route['custrecord_invd_payment_method'], rule['custrecord_invd_payment_method'], score]);
        return score;
    }

    // Each condition that is in the rule will increase the score by:
    // 1 if the rule is a default rule (*)
    // 2 if the rule is an exact match, or
    // return -1 if any condition fails
    function getScore(route: Conditions, rule: Conditions, key: AvailableConditions): number {
        if (!rule[key]) {
            return 1;
        } else if (route[key] === rule[key]) {
            return 2;
        }
        return -1; // a condition did not match was found
    }

    return {
        fetch: function (currency: string, paymentType: string) {
            let maxScore = -1;
            let matchedRule = null;

            const result = search
                .create({
                    type: 'currency',
                    filters: [
                        search.createFilter({
                            name: 'symbol',
                            operator: search.Operator.IS,
                            values: currency.toUpperCase(),
                        }),
                    ],
                })
                .run()
                .getRange({
                    start: 0,
                    end: 1
                });
            if (!result || !result[0]) {
                return null;
            }
            const netsuiteCurrency = result[0].id;
            log.debug('netsuiteCurrency', netsuiteCurrency);
            const route = {
                custrecord_invd_currency: netsuiteCurrency,
                custrecord_invd_payment_method: paymentType,
            };

            const columns = [
                'custrecord_invd_undeposited_funds',
                'custrecord_ivnd_deposit_bank_account',
                'custrecord_invd_currency',
                'custrecord_invd_payment_method',
            ];


            search
                .create({
                    type: 'customrecord_invd_deposit_mapping',
                    columns: columns,
                    filters: [
                        search.createFilter({
                            name: 'isinactive',
                            operator: search.Operator.IS,
                            values: 'F',
                        }),
                    ],
                })
                .run()
                .each(function (result) {
                    log.debug('Deposit rule', result);
                    const score = calculateScore(route, {
                        custrecord_invd_currency: result.getValue('custrecord_invd_currency') as string,
                        custrecord_invd_payment_method: result.getText('custrecord_invd_payment_method'),
                    });
                    log.debug('matchedRule', [maxScore, score]);
                    if (score > maxScore) {
                        maxScore = score;
                        matchedRule = {
                            custrecord_invd_undeposited_funds: result.getValue('custrecord_invd_undeposited_funds') as string,
                            custrecord_ivnd_deposit_bank_account: parseInt(result.getValue('custrecord_ivnd_deposit_bank_account') as string),
                        };
                    }
                    return true;
                });
            return matchedRule;
        },
    };
});
