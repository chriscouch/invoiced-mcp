<?php

namespace App\Integrations\NetSuite\Writers;

use App\AccountsReceivable\Models\Note;

/**
 * Class NetSuiteNoteAdapter.
 *
 * @template-extends    AbstractNetSuiteObjectWriter<Note>
 *
 * @property Note $model
 */
class NetSuiteNoteWriter extends AbstractNetSuiteObjectWriter
{
    public function __construct(Note $model)
    {
        parent::__construct($model);
    }

    /**
     * Get url deployment id.
     */
    public function getDeploymentId(): string
    {
        return 'customdeploy_invd_note_restlet';
    }

    /**
     * Gets url script id.
     */
    public function getScriptId(): string
    {
        return 'customscript_invd_note_restlet';
    }

    public function toArray(): array
    {
        $model = $this->model;
        $customer = $model->customer;
        $customerMapping = $this->getCustomerMapping($customer);

        return [
            'customer_id' => $customerMapping,
            'invoiced_id' => $model->id(),
            'created_at' => $model->created_at,
            'notes' => $model->notes,
        ];
    }

    public function getReverseMapping(): ?string
    {
        return (string) $this->model->id();
    }

    public function shouldCreate(): bool
    {
        return true;
    }
}
