<?php

use Phinx\Migration\AbstractMigration;

final class CronJob extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('CronJobs', ['id' => false]);
        $table->addColumn('id', 'string')
            ->addColumn('last_ran', 'integer', ['null' => true, 'default' => null])
            ->addColumn('last_run_succeeded', 'boolean', ['null' => true, 'default' => null])
            ->addColumn('last_run_output', 'text', ['null' => true, 'default' => null])
            ->create();
    }
}
