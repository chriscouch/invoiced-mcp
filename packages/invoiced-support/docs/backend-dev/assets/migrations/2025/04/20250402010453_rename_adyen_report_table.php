<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RenameAdyenReportTable extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->execute('DELETE FROM AdyenReportImports WHERE file like "https%"');
        $this->table('AdyenReportImports')
            ->rename('AdyenReports')
            ->update();
        $this->table('AdyenReports')
            ->addIndex(['file'], ['unique' => true])
            ->update();
        $this->table('AdyenReports')
            ->renameColumn('file', 'filename')
            ->update();
    }
}
