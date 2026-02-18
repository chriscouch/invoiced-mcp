<?php

namespace App\Integrations\NetSuite\Writers;

use App\AccountsReceivable\Models\CreditNote;

/**
 * @template-extends    AbstractNetSuiteDocumentWriter<CreditNote>
 *
 * @property CreditNote $model
 */
class NetSuiteCreditNoteWriter extends AbstractNetSuiteDocumentWriter
{
    /**
     * Get url deployment id.
     */
    public function getDeploymentId(): string
    {
        return 'customdeploy_invd_credit_note_restlet';
    }

    /**
     * Gets url script id.
     */
    public function getScriptId(): string
    {
        return 'customscript_invd_credit_note_restlet';
    }

    public function getReverseMapping(): ?string
    {
        return $this->getCreditNoteMapping($this->model);
    }
}
