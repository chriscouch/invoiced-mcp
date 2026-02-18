import * as NUrl from "N/url";
import * as NHttps from "N/https";
/**
 * @NApiVersion 2.x
 * @NScriptType ClientScript
 * @NModuleScope Public
 */
type defineCallback = (
    url: typeof NUrl,
    https: typeof NHttps,
) => object;

declare function define(arg: string[], fn: defineCallback): void;

define([
    'N/url',
    'N/https',
], function(
     url, https ) {
    function syncNow(): void {
        const suitlet = url.resolveScript({
            scriptId: 'customscript_invd_sync_now',
            deploymentId: 'customdeploy_invd_sync_now',
        });
        https.post({
            url: suitlet + '&sync=1',
            body: null,
        });
        setTimeout(function(){
            window.document.location = suitlet;
        },1000);
    }

    return {
        pageInit: () => null,
        syncNow: syncNow,
    };
});
