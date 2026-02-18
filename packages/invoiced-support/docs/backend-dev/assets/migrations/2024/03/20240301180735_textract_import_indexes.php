<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class TextractImportIndexes extends MultitenantModelMigration
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
        $this->table('TextractImports')
            ->changePrimaryKey(null)
            ->addIndex('job_id', ['unique' => true])
            ->addIndex('parent_job_id')
            ->update();
        $this->execute('ALTER TABLE TextractImports ADD id INT AUTO_INCREMENT PRIMARY KEY');
    }
}
