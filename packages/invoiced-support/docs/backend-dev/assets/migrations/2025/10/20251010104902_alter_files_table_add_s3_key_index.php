<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AlterFilesTableAddS3KeyIndex extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->execute('SET SESSION max_statement_time=0');
        $table = $this->table('Files');
        if (!$table->hasIndex(['key']) ){
            $table->addIndex(['key'])->update();
        }
    }
}
