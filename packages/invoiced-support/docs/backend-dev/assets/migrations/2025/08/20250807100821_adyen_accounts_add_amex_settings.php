<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AdyenAccountsAddAmexSettings extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AdyenAccounts')
            ->addColumn('amex_mid_number', 'string', ['length'=> 10, 'null' => true, 'default' => null])
            ->addColumn('amex_service_level', 'enum', ['values' => ['noContract', 'gatewayContract', 'paymentDesignatorContract'], 'null' => true, 'default' => 'noContract'])
            ->update();
        $this->execute('UPDATE AdyenAccounts SET amex_service_level = "noContract"');
    }
}