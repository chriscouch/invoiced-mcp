<?php

namespace App\Tests\PaymentProcessing\Models;

use App\Core\Orm\Model;
use App\Core\Utils\RandomString;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\TokenizationFlowStatus;
use App\PaymentProcessing\Models\TokenizationFlow;
use App\Tests\ModelTestCase;

/**
 * @extends ModelTestCase<TokenizationFlow>
 */
class TokenizationFlowTest extends ModelTestCase
{
    private static TokenizationFlow $tokenizationFlow;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCustomer();
    }

    protected function getModelCreate(): Model
    {
        $tokenizationFlow = new TokenizationFlow();
        $tokenizationFlow->identifier = RandomString::generate();
        $tokenizationFlow->status = TokenizationFlowStatus::CollectPaymentDetails;
        $tokenizationFlow->initiated_from = PaymentFlowSource::Api;
        self::$tokenizationFlow = $tokenizationFlow;

        return $tokenizationFlow;
    }

    public function testCreate(): void
    {
        parent::testCreate();
        $this->assertEquals(self::$company->id(), self::$tokenizationFlow->tenant_id);
    }

    protected function getExpectedToArray($model, array &$output): array
    {
        return [
            'canceled_at' => null,
            'completed_at' => null,
            'created_at' => self::$tokenizationFlow->created_at,
            'customer_id' => null,
            'email' => null,
            'id' => self::$tokenizationFlow->id,
            'identifier' => self::$tokenizationFlow->identifier,
            'initiated_from' => 'api',
            'make_payment_source_default' => null,
            'payment_method' => null,
            'payment_source_id' => null,
            'payment_source_type' => null,
            'return_url' => null,
            'sign_up_page_id' => null,
            'status' => 'collect_payment_details',
            'updated_at' => self::$tokenizationFlow->updated_at,
        ];
    }

    protected function getModelEdit($model): TokenizationFlow
    {
        $model->return_url = 'https://example.com';

        return $model;
    }
}
