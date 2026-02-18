<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class DisableSmartShrinking extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('PdfTemplates')
            ->addColumn('disable_smart_shrinking', 'boolean')
            ->update();
    }
}
