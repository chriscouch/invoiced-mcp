<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class EmailSendingAr extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('INSERT IGNORE INTO ProductFeatures (product_id, feature) SELECT id, "email_sending" FROM Products WHERE name="Accounts Receivable"');
    }
}
