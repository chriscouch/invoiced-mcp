<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentLinkCollectionSettings extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PaymentLinks')
            ->addColumn('collect_billing_address', 'boolean')
            ->addColumn('collect_shipping_address', 'boolean')
            ->addColumn('collect_phone_number', 'boolean')
            ->addColumn('terms_of_service_url', 'string', ['length' => 5000, 'null' => true, 'default' => null])
            ->update();
    }
}
