<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveModuleFeatureFlag extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('DELETE FROM Features WHERE feature LIKE "module_%"');
    }
}
