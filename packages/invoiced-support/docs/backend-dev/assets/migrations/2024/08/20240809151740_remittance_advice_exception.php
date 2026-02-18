<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemittanceAdviceException extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('RemittanceAdviceLines')
            ->addColumn('exception', 'smallinteger', ['null' => true, 'default' => null])
            ->update();
    }
}
