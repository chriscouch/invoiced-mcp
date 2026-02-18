<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CardIssuingCountry extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Cards')
            ->addColumn('issuing_country', 'string', ['length' => 2, 'null' => true, 'default' => null])
            ->update();
    }
}
