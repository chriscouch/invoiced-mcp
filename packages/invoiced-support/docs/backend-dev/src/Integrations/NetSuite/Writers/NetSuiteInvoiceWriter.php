<?php

namespace App\Integrations\NetSuite\Writers;

use App\AccountsReceivable\Models\Invoice;

/**
 * @template-extends    AbstractNetSuiteDocumentWriter<Invoice>
 *
 * @property Invoice $model
 */
class NetSuiteInvoiceWriter extends AbstractNetSuiteDocumentWriter
{
    /**
     * Get url deployment id.
     */
    public function getDeploymentId(): string
    {
        return 'customdeploy_invd_invoice_model_restlet';
    }

    /**
     * Gets url script id.
     */
    public function getScriptId(): string
    {
        return 'customscript_invd_invoice_model_restlet';
    }

    public function getReverseMapping(): ?string
    {
        return $this->getInvoiceMapping($this->model);
    }
}
