<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentLinkName extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PaymentLinks')
            ->addColumn('name', 'string')
            ->update();
        $this->execute('UPDATE PaymentLinks SET name="Payment Link"');

        $this->table('PaymentLinkSessions')
            ->addColumn('payment_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('payment_id', 'Payments', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();
    }
}
