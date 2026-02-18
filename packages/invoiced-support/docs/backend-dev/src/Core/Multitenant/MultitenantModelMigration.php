<?php

namespace App\Core\Multitenant;

use Phinx\Db\Table;
use Phinx\Migration\AbstractMigration;

abstract class MultitenantModelMigration extends AbstractMigration
{
    public function addTenant(Table $table): void
    {
        $table->addColumn('tenant_id', 'integer')
            ->addForeignKey('tenant_id', 'Companies', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
    }

    public function ensureInstant(): void
    {
        $this->execute("SET SESSION alter_algorithm='INSTANT'");
    }

    public function ensureInstantEnd(): void
    {
        $this->execute("SET SESSION alter_algorithm='DEFAULT'");
    }

    public function disableMaxStatementTimeout(): void
    {
        $this->execute('SET SESSION max_statement_time=0');
    }

    public function readCommitted(): void
    {
        $this->execute("SET SESSION tx_isolation = 'read-committed'");
    }

    public function disableForeignKeyChecks(): void
    {
        $this->execute('SET FOREIGN_KEY_CHECKS=0');
    }

    public function enableForeignKeyChecks(): void
    {
        $this->execute('SET FOREIGN_KEY_CHECKS=1');
    }
}
