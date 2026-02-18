<?php

namespace App\Tests\Integrations\Adyen\Models;

use App\Integrations\Adyen\Models\AdyenAccount;
use App\Tests\ModelTestCase;
use Carbon\CarbonImmutable;

/**
 * @extends ModelTestCase<AdyenAccount>
 */
class AdyenAccountTest extends ModelTestCase
{
    protected function getModelCreate(): AdyenAccount
    {
        return new AdyenAccount();
    }

    protected function getExpectedToArray($model, array &$output): array
    {
        return [
            'account_holder_id' => null,
            'activated_at' => null,
            'balance_account_id' => null,
            'business_line_id' => null,
            'created_at' => $model->created_at,
            'has_onboarding_problem' => null,
            'id' => $model->id,
            'industry_code' => null,
            'last_onboarding_reminder_sent' => null,
            'legal_entity_id' => null,
            'onboarding_started_at' => null,
            'pricing_configuration_id' => null,
            'reference' => null,
            'statement_descriptor' => null,
            'store_id' => null,
            'terms_of_service_acceptance_date' => null,
            'terms_of_service_acceptance_ip' => null,
            'terms_of_service_acceptance_user_id' => null,
            'terms_of_service_acceptance_version' => null,
            'updated_at' => $model->updated_at,
        ];
    }

    protected function getModelEdit($model): AdyenAccount
    {
        $model->terms_of_service_acceptance_ip = '127.0.0.1';
        $model->terms_of_service_acceptance_date = CarbonImmutable::now();
        $model->industry_code = '1234';

        return $model;
    }

    public function testStatementDescriptor(): void
    {
        $adyenAccount = new AdyenAccount();
        $this->assertEquals('TEST', $adyenAccount->getStatementDescriptor());
        $adyenAccount->statement_descriptor = 'custom descriptor';
        $this->assertEquals('custom descriptor', $adyenAccount->getStatementDescriptor());
    }
}
