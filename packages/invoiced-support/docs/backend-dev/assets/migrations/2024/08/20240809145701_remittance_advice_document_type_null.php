<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemittanceAdviceDocumentTypeNull extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('RemittanceAdviceLines')
            ->changeColumn('document_type', 'smallinteger', ['null' => true, 'default' => null])
            ->update();
    }
}
