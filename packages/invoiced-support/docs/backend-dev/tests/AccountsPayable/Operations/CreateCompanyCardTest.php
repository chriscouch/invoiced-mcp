<?php

namespace App\Tests\AccountsPayable\Operations;

use App\AccountsPayable\Models\CompanyCard;
use App\AccountsPayable\Operations\CreateCompanyCard;
use App\AccountsPayable\Operations\DeleteCompanyCard;
use App\Tests\AppTestCase;
use Mockery;
use Stripe\StripeClient;

class CreateCompanyCardTest extends AppTestCase
{
    private static CompanyCard $companyCard;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getCreate(): CreateCompanyCard
    {
        return self::getService('test.create_company_card');
    }

    private function getDelete(): DeleteCompanyCard
    {
        return self::getService('test.delete_company_card');
    }

    public function testStart(): void
    {
        $stripe = Mockery::mock(StripeClient::class);
        $customer = Mockery::mock();
        $stripe->customers = $customer;
        $customer->shouldReceive('all')
            ->andReturn((object) ['data' => []]);
        $customer->shouldReceive('create')
            ->andReturn((object) ['id' => 'cust_test']);
        $intents = Mockery::mock();
        $stripe->setupIntents = $intents;
        $intents->shouldReceive('create')
            ->withArgs([['customer' => 'cust_test']])
            ->andReturn((object) ['client_secret' => 'si_test_secret']);

        $create = $this->getCreate();
        $create->setStripe($stripe);

        $expected = [
            'client_secret' => 'si_test_secret',
        ];
        $this->assertEquals($expected, $create->start(self::$company));
    }

    public function testFinish(): void
    {
        $setupIntent = (object) [
            'customer' => 'cust_test',
            'payment_method' => (object) [
                'id' => 'pm_test',
                'card' => (object) [
                    'brand' => 'Visa',
                    'last4' => '4242',
                    'exp_month' => 8,
                    'exp_year' => 2030,
                    'funding' => 'credit',
                    'country' => 'US',
                ],
            ],
        ];
        $stripe = Mockery::mock(StripeClient::class);
        $intents = Mockery::mock();
        $stripe->setupIntents = $intents;
        $intents->shouldReceive('retrieve')
            ->withArgs(['si_test', ['expand' => ['payment_method']]])
            ->andReturn($setupIntent);

        $create = $this->getCreate();
        $create->setStripe($stripe);

        $card = $create->finish('si_test');

        $expected = [
            'brand' => 'Visa',
            'created_at' => $card->created_at,
            'deleted' => null,
            'deleted_at' => null,
            'exp_month' => 8,
            'exp_year' => 2030,
            'funding' => 'credit',
            'id' => $card->id(),
            'issuing_country' => 'US',
            'last4' => '4242',
            'updated_at' => $card->updated_at,
        ];
        $this->assertEquals($expected, $card->toArray());
        $this->assertEquals('stripe', $card->gateway);
        $this->assertEquals('cust_test', $card->stripe_customer);
        $this->assertEquals('pm_test', $card->stripe_payment_method);
        self::$companyCard = $card;
    }

    /**
     * @depends testFinish
     */
    public function testDelete(): void
    {
        $stripe = Mockery::mock(StripeClient::class);
        $paymentMethods = Mockery::mock();
        $stripe->paymentMethods = $paymentMethods;
        $paymentMethods->shouldReceive('detach')->once();
        $delete = $this->getDelete();
        $delete->setStripe($stripe);

        $delete->delete(self::$companyCard);

        $this->assertTrue(self::$companyCard->deleted);
    }
}
