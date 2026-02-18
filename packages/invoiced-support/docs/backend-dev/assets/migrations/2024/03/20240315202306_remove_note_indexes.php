<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveNoteIndexes extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('Notes');
        if ($table->hasIndex('invoice_id')) {
            $table->removeIndex('invoice_id')
                ->update();
        }
        if ($table->hasIndex('customer_id')) {
            $table->removeIndex('customer_id')
                ->update();
        }
    }
}
