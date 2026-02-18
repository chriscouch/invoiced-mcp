<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemittanceAdviceStatus extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('RemittanceAdvice')
            ->addColumn('status', 'smallinteger')
            ->update();

        $this->table('RemittanceAdviceLines')
            ->addColumn('document_type', 'smallinteger')
            ->update();
    }
}
