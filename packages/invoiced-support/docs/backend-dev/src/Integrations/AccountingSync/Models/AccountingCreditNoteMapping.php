<?php

namespace App\Integrations\AccountingSync\Models;

use App\AccountsReceivable\Models\CreditNote;
use App\Integrations\Enums\IntegrationType;
use App\Core\Orm\Property;

/**
 * @property CreditNote $credit_note
 * @property int        $credit_note_id
 */
class AccountingCreditNoteMapping extends AbstractMapping
{
    protected static function getProperties(): array
    {
        return [
            'credit_note' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                belongs_to: CreditNote::class,
            ),
            'accounting_id' => new Property(
                required: true,
            ),
            'source' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['accounting_system', 'invoiced']],
            ),
        ];
    }

    protected static function getIDProperties(): array
    {
        return ['credit_note_id'];
    }

    public static function findForCreditNote(CreditNote $creditNote, IntegrationType $integration): ?self
    {
        return self::where('integration_id', $integration->value)
            ->where('credit_note_id', $creditNote)
            ->oneOrNull();
    }
}
