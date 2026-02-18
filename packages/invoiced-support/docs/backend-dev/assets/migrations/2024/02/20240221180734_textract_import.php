<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class TextractImport extends MultitenantModelMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('TextractImports', ['id' => false, 'primary_key' => 'job_id']);
        $this->addTenant($table);

        $table
            ->addColumn('job_id', 'string', ['length' => 64])
            ->addColumn('parent_job_id', 'string', ['length' => 64])
            ->addColumn('file_id', 'integer')
            ->addColumn('vendor_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('vendor_credit_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('bill_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('data', 'json')
            ->addColumn('status', 'tinyinteger')
            ->addForeignKey('file_id', 'Files', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addTimestamps()
            ->create();
    }
}
