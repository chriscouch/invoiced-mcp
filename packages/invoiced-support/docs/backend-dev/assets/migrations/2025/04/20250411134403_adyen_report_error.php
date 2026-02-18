<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AdyenReportError extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AdyenReports')
            ->addColumn('error', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
