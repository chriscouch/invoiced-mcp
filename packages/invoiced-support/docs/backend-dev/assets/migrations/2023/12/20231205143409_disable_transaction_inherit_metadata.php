<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DisableTransactionInheritMetadata extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('UPDATE AccountsReceivableSettings SET transactions_inherit_invoice_metadata=0');
    }
}
