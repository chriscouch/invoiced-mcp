<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CanceledIndustry extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('CanceledCompanies')
            ->removeColumn('pipedrive_deal_id')
            ->removeColumn('not_charged')
            ->changeColumn('address1', 'string', ['length' => 255, 'null' => true, 'default' => null])
            ->changeColumn('stripe_customer', 'string', ['length' => 30])
            ->addColumn('invoiced_customer', 'string', ['length' => 30])
            ->changeColumn('canceled_reason', 'string', ['length' => 30])
            ->changeColumn('converted_from', 'string', ['length' => 50])
            ->changeColumn('referred_by', 'string', ['length' => 50])
            ->addColumn('industry', 'string', ['length' => 50, 'null' => true, 'default' => null])
            ->update();
    }
}
