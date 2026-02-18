<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MigrateFlywireAmountsToDecimal extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->table('FlywirePayments')
            ->changeColumn('amount_from', 'decimal', ['precision' => 20, 'scale' => 10])
            ->changeColumn('amount_to', 'decimal', ['precision' => 20, 'scale' => 10])
            ->update();
        $this->table('FlywireRefunds')
            ->changeColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->changeColumn('amount_to', 'decimal', ['precision' => 20, 'scale' => 10])
            ->update();
        $this->table('FlywireDisbursements')
            ->changeColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->update();
        $this->table('FlywirePayouts')
            ->changeColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->update();
        $this->table('FlywireRefundBundles')
            ->changeColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->update();

        $this->execute('UPDATE FlywirePayments SET amount_from = amount_from / 100, amount_to = amount_to / 100');
        $this->execute('UPDATE FlywireRefunds SET amount = amount / 100, amount_to = amount_to / 100');
        $this->execute('UPDATE FlywireDisbursements SET amount = amount / 100');
        $this->execute('UPDATE FlywirePayouts SET amount = amount / 100');
        $this->execute('UPDATE FlywireRefundBundles SET amount = amount / 100');
    }
}
