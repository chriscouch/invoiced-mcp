<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DwollaOnboardingSession extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('DwollaOnboardingApplications');
        $this->addTenant($table);
        $table->addColumn('user_id', 'integer')
            ->addColumn('ip_address', 'string')
            ->addColumn('user_agent', 'string')
            ->addColumn('business_name', 'string', ['null' => true, 'default' => null])
            ->addColumn('business_type', 'string', ['null' => true, 'default' => null])
            ->addColumn('entity_type', 'string', ['null' => true, 'default' => null])
            ->addColumn('doing_business_as', 'string', ['null' => true, 'default' => null])
            ->addColumn('address1', 'string', ['null' => true, 'default' => null])
            ->addColumn('address2', 'string', ['null' => true, 'default' => null])
            ->addColumn('city', 'string', ['null' => true, 'default' => null])
            ->addColumn('state', 'string', ['null' => true, 'default' => null])
            ->addColumn('postal_code', 'string', ['null' => true, 'default' => null])
            ->addColumn('ein', 'string', ['null' => true, 'default' => null])
            ->addColumn('business_classification', 'string', ['null' => true, 'default' => null])
            ->addColumn('controller_first_name', 'string', ['null' => true, 'default' => null])
            ->addColumn('controller_last_name', 'string', ['null' => true, 'default' => null])
            ->addColumn('controller_title', 'string', ['null' => true, 'default' => null])
            ->addColumn('controller_date_of_birth', 'text', ['null' => true, 'default' => null])
            ->addColumn('controller_address1', 'string', ['null' => true, 'default' => null])
            ->addColumn('controller_address2', 'string', ['null' => true, 'default' => null])
            ->addColumn('controller_address3', 'string', ['null' => true, 'default' => null])
            ->addColumn('controller_city', 'string', ['null' => true, 'default' => null])
            ->addColumn('controller_state', 'string', ['null' => true, 'default' => null])
            ->addColumn('controller_postal_code', 'string', ['null' => true, 'default' => null])
            ->addColumn('controller_country', 'string', ['null' => true, 'default' => null])
            ->addColumn('controller_ssn', 'text', ['null' => true, 'default' => null])
            ->addColumn('controller_passport_number', 'text', ['null' => true, 'default' => null])
            ->addColumn('dwolla_customer_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('customer_created_at', 'timestamp', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('user_id', 'Users', 'id')
            ->create();
    }
}
