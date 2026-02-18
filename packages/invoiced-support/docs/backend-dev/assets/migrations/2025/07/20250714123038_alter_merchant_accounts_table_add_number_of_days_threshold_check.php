<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AlterMerchantAccountsTableAddNumberOfDaysThresholdCheck extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('MerchantAccounts')
            ->addColumn('top_up_threshold_num_of_days', 'integer', ['default' => 14])
            ->update();
    }
}
