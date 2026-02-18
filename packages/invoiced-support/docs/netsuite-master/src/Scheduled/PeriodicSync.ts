import {scheduledScript} from "../definitions/ScheduledScript";
import {SyncInterface} from "./Sync";

type defineCallback = (
    Sync: SyncInterface
) => scheduledScript;

declare function define(arg: string[], fn: defineCallback): void;

/**
 * @NApiVersion 2.0
 * @NModuleScope Public
 * @NScriptType ScheduledScript
 */
define([
    'tmp/src/Scheduled/Sync',
], function(
    Sync) {
    //half of NS allowed 10,000
    const GOVERNANCE_LIMIT = 5000;

    return {
        execute: () => Sync.execute(GOVERNANCE_LIMIT),
    }
});