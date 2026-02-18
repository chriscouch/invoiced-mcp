"use strict";

/* globals jQuery, SUITELET_URL */

namespace UpdateContacts {
    declare const SUITELET_URL: string;

    let splits: number[] = [];
    let addInterval: null | NodeJS.Timeout;
    let removeInterval: null | NodeJS.Timeout;

    jQuery(function () {
        splits = getIds();
        jQuery("#addcompany").on('click', () => launchAddInterval());
        jQuery("#company_splits").on('click', (item) => {
            if (!matchRemoveButton(getHref(item.target))) {
                return;
            }
            launchRemoveInterval()
        });
    });

    function launchAddInterval() {
        if (addInterval) {
            return;
        }
        addInterval = setInterval(() => {
            const newSplits = getIds();
            const diff = difference(newSplits, splits);
            if (diff.length) {
                for (const i in diff) {
                    jQuery.ajax({
                        url: SUITELET_URL + "&action=add&customer_id=" + diff[i],
                        method: "POST"
                    });
                }
                splits = newSplits;
                if (addInterval) {
                    clearInterval(addInterval);
                    addInterval = null;
                }
            }
        }, 250);
    }

    function launchRemoveInterval() {
        if (removeInterval) {
            return;
        }
        removeInterval = setInterval(() => {
            const newSplits = getIds();
            const diff = difference(splits, newSplits);
            if (diff.length) {
                for (const i in diff) {
                    jQuery.ajax({
                        url: SUITELET_URL + "&action=remove&customer_id=" + diff[i],
                        method: "POST"
                    });
                }
                splits = newSplits;
                if (removeInterval) {
                    clearInterval(removeInterval);
                    removeInterval = null;
                }
            }
        }, 250);
    }


    function difference(a: number[], b: number[]) {
        return a.filter(function (i) {
            return b.indexOf(i) < 0;
        });
    }

    function matchRemoveButton(item: string): number | null {
        const result: RegExpMatchArray | null = item.match("remove_company.([0-9]+)");
        return result ? parseInt(result.pop() as string) : null;
    }

    function getIds(): number[] {
        return jQuery("#company_splits a").map((_a, b) => {
            return matchRemoveButton(getHref(b));
        }).toArray();
    }

    function getHref(item: HTMLElement): string {
        return jQuery(item).attr('href') || '';
    }

}