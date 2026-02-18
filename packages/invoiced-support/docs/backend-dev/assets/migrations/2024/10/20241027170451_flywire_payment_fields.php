<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FlywirePaymentFields extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('FlywirePayments')
            ->addColumn('initiated_at', 'timestamp')
            ->addColumn('expiration_date', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('payment_method_type', 'string', ['null' => true, 'default' => null])
            ->addColumn('payment_method_brand', 'string', ['null' => true, 'default' => null])
            ->addColumn('payment_method_card_classification', 'string', ['null' => true, 'default' => null])
            ->addColumn('payment_method_card_expiration', 'string', ['null' => true, 'default' => null])
            ->addColumn('payment_method_last4', 'string', ['null' => true, 'default' => null])
            ->removeColumn('external_reference')
            ->removeColumn('reversed_amount')
            ->removeColumn('reversed_type')
            ->removeColumn('client_reason')
            ->removeColumn('entity_id')
            ->update();
    }
}
