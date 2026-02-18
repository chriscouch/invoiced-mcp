<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Industry extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Companies')
            ->removeColumn('pipedrive_deal_id')
            ->removeColumn('digest_frequency')
            ->removeColumn('next_digest')
            ->removeColumn('not_charged')
            ->removeColumn('digest_email')
            ->changeColumn('time_zone', 'string', ['length' => 30])
            ->changeColumn('address1', 'string', ['length' => 255, 'null' => true, 'default' => null])
            ->changeColumn('stripe_customer', 'string', ['length' => 30])
            ->changeColumn('invoiced_customer', 'string', ['length' => 30])
            ->changeColumn('canceled_reason', 'string', ['length' => 30])
            ->changeColumn('converted_from', 'string', ['length' => 50])
            ->changeColumn('highlight_color', 'string', ['length' => 7])
            ->changeColumn('referred_by', 'string', ['length' => 50])
            ->addColumn('industry', 'string', ['length' => 50, 'null' => true, 'default' => null])
            ->update();
    }
}
