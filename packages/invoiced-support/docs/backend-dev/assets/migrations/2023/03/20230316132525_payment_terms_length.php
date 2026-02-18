<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentTermsLength extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->ensureInstant();
        $this->table('Invoices')
            ->changeColumn('payment_terms', 'string', ['null' => true, 'default' => null])
            ->update();

        $this->table('Estimates')
            ->changeColumn('payment_terms', 'string', ['null' => true, 'default' => null])
            ->update();

        $this->table('Settings')
            ->changeColumn('payment_terms', 'string', ['null' => true, 'default' => null])
            ->update();
        $this->ensureInstantEnd();
    }
}
