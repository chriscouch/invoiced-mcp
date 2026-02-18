<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FlowFormSubmission extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('FlowFormSubmissions')
            ->addColumn('reference', 'string')
            ->addColumn('data', 'text')
            ->addTimestamps()
            ->addIndex('reference')
            ->create();
    }
}
