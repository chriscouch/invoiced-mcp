<?php

namespace App\Tests\PaymentProcessing\Models;

use App\PaymentProcessing\Gateways\MockGateway;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\PaymentMethod;

class CardTest extends PaymentSourceTestBase
{
    public function getModel(): string
    {
        return Card::class;
    }

    public function expectedMethod(): string
    {
        return PaymentMethod::CREDIT_CARD;
    }

    public function expectedTypeName(): string
    {
        return 'Card';
    }

    public function getCreateParams(): array
    {
        return [
            'funding' => 'credit',
            'brand' => 'Visa',
            'last4' => 1234,
            'exp_month' => 2,
            'exp_year' => 2016,
            'gateway_id' => 'card_test',
            'issuing_country' => null,
            'chargeable' => true,
        ];
    }

    public function expectedArray(): array
    {
        return [
            'id' => self::$source->id(),
            'object' => 'card',
            'funding' => 'credit',
            'brand' => 'Visa',
            'last4' => 1234,
            'exp_month' => 2,
            'exp_year' => 2016,
            'issuing_country' => null,
            'gateway' => MockGateway::ID,
            'gateway_id' => 'card_test',
            'gateway_customer' => null,
            'gateway_setup_intent' => null,
            'merchant_account' => null,
            'chargeable' => true,
            'failure_reason' => null,
            'created_at' => self::$source->created_at,
            'updated_at' => self::$source->updated_at,
            'receipt_email' => null,
            'customer_id' => self::$customer->id,
        ];
    }

    public function editSource(): void
    {
        self::$source->exp_month = 5; /* @phpstan-ignore-line */
    }

    public function testToString(): void
    {
        $card = new Card();
        $card->last4 = '1234';
        $card->brand = 'Discover';
        $card->exp_month = 1;
        $card->exp_year = 2026;

        $this->assertEquals('Discover *1234 (expires Jan \'26)', $card->toString());

        $this->assertEquals('Discover *1234', $card->toString(true));
    }

    public function testNeedsVerification(): void
    {
        $card = new Card();
        $this->assertFalse($card->needsVerification());
    }
}
