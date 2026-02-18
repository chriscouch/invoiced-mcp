<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AdyenReportErrorText extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AdyenReports')
            ->changeColumn('error', 'text', ['null' => true, 'default' => null])
            ->update();
    }
}
