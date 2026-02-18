<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveUnusedGateways extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('UPDATE MerchantAccounts SET deleted=1 WHERE gateway IN ("amex", "affinipay", "cpacharge", "mes", "worldpay_mp")');
    }
}
