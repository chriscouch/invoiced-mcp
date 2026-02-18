<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentLinkSettings extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('CustomerPortalSettings')
            ->removeColumn('payment_links')
            ->update();
    }
}
