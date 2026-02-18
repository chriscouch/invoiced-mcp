<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class SmtpAccount extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('SmtpAccounts', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('host', 'string')
            ->addColumn('username', 'string')
            ->addColumn('password_enc', 'text')
            ->addColumn('port', 'integer')
            ->addColumn('encryption', 'string')
            ->addColumn('auth_mode', 'string')
            ->addColumn('fallback_on_failure', 'boolean', ['default' => true])
            ->addColumn('last_error_message', 'string', ['null' => true, 'default' => null, 'length' => 1000])
            ->addColumn('last_error_timestamp', 'integer', ['null' => true, 'default' => null])
            ->addColumn('last_send_successful', 'boolean', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();
    }
}
