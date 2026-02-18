<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AdyenOnboardingFields extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AdyenAccounts')
            ->addColumn('onboarding_started_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('activated_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('last_onboarding_reminder_sent', 'date', ['null' => true, 'default' => null])
            ->addColumn('has_onboarding_problem', 'boolean')
            ->addColumn('statement_descriptor', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
