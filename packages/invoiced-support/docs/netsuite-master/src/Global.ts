/**
 * @NApiVersion 2.x
 * @NModuleScope Public
 */
enum Globals {
    INVOICED_RESTRICT_SYNC_TRANSACTION_BODY_FIELD = "custbody_invd_import",
    INVOICED_RESTRICT_SYNC_ENTITY_FIELD = "custentity_invd_import",
    INVOICED_CLIENT_VIEW_URL_FIELD = "custbody_invd_invoice_url",
    IS_INVOICED_CONTACT_FIELD = "custentity_invd_contact",
    DIVISION_KEY = "custrecord_invd_division_key",
    BUNDLE_VERSION = "5.1.0",
}
declare function define(arg: string[], fn: () => typeof Globals): void;
define([], () => Globals);