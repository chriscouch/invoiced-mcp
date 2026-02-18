<?php

use Phinx\Migration\AbstractMigration;

final class CompanySamlSettings extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('CompanySamlSettings', ['id' => false]);
        $table->addColumn('company_id', 'integer')
            ->addColumn('domain', 'string', ['length' => 30])
            ->addColumn('enabled', 'boolean')
            ->addColumn('entity_id', 'string')
            ->addColumn('sso_url', 'string')
            ->addColumn('sso_binding', 'string')
            ->addColumn('sls_url', 'string', ['null' => true, 'default' => null])
            ->addColumn('sls_binding', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addColumn('cert', 'string')
            ->addIndex('domain', ['unique' => true])
            ->addIndex('company_id', ['unique' => true])
            ->addForeignKey('company_id', 'Companies', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
