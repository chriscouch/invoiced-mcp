<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RenameFreeProducts extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('UPDATE Products SET name="Accounts Receivable Free" WHERE name="Accounts Receivable"');
        $this->execute('UPDATE Products SET name="Accounts Payable Free" WHERE name="Accounts Payable"');
    }
}
