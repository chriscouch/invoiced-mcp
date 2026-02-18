<?php

namespace App\Integrations\AccountingSync\Models;

use App\AccountsReceivable\Models\Invoice;
use App\Integrations\Enums\IntegrationType;
use App\Core\Orm\Property;

/**
 * @property Invoice $invoice
 * @property int     $invoice_id
 */
class AccountingInvoiceMapping extends AbstractMapping
{
    protected static function getIDProperties(): array
    {
        return ['invoice_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'invoice' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                belongs_to: Invoice::class,
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

    public static function findForInvoice(Invoice $invoice, IntegrationType $integration): ?self
    {
        return self::where('integration_id', $integration->value)
            ->where('invoice_id', $invoice)
            ->oneOrNull();
    }
}
