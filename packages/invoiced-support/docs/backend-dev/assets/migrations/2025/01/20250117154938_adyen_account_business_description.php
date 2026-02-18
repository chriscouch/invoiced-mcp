<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AdyenAccountBusinessDescription extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AdyenAccounts')
            ->addColumn('industry_code', 'string', ['null' => true, 'default' => null])
            ->addColumn('terms_of_service_acceptance_date', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('terms_of_service_acceptance_ip', 'string', ['null' => true, 'default' => null])
            ->addColumn('terms_of_service_acceptance_user_id', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
