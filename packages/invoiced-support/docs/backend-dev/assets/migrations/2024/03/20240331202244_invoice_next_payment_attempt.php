<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoiceNextPaymentAttempt extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Invoices')
            ->addIndex('next_payment_attempt')
            ->update();
    }
}
