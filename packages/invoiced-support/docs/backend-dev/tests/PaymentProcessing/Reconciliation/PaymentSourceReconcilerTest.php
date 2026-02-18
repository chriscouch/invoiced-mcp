<?php

namespace App\Tests\PaymentProcessing\Reconciliation;

use App\Core\Statsd\StatsdClient;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\Tests\AppTestCase;

class PaymentSourceReconcilerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    public function testReconcileCard(): void
    {
        $card = new CardValueObject(
            customer: self::$customer,
            gateway: 'invoiced',
            gatewayId: 'card_test',
            merchantAccount: new MerchantAccount(['id' => 1234]),
            chargeable: true,
            brand: 'Visa',
            funding: 'credit',
            last4: '1234',
            expMonth: 1,
            expYear: 2020,
        );

        $reconciler = $this->getReconciler();

        $card = $reconciler->reconcile($card);

        $this->assertInstanceOf(Card::class, $card);
        $expected = [
            'brand' => 'Visa',
            'chargeable' => true,
            'created_at' => $card->created_at,
            'customer_id' => self::$customer->id,
            'exp_month' => 1,
            'exp_year' => 2020,
            'failure_reason' => null,
            'funding' => 'credit',
            'gateway' => 'invoiced',
            'gateway_customer' => null,
            'gateway_id' => 'card_test',
            'gateway_setup_intent' => null,
            'id' => $card->id(),
            'issuing_country' => null,
            'last4' => '1234',
            'merchant_account' => 1234,
            'object' => 'card',
            'receipt_email' => null,
            'updated_at' => $card->updated_at,
        ];
        $this->assertEquals($expected, $card->toArray());
    }

    public function testReconcileCardBrand(): void
    {
        $card = new CardValueObject(
            customer: self::$customer,
            gateway: 'invoiced',
            gatewayId: null,
            merchantAccount: new MerchantAccount(['id' => 1234]),
            chargeable: true,
            brand: 'Visa',
            funding: 'credit',
            last4: '1234',
            expMonth: 1,
            expYear: 2020,
        );

        $card2 = new CardValueObject(
            customer: self::$customer,
            gateway: 'invoiced',
            gatewayId: null,
            merchantAccount: new MerchantAccount(['id' => 5234]),
            chargeable: true,
            brand: 'Mastercard',
            funding: 'credit',
            last4: '2234',
            expMonth: 1,
            expYear: 2021,
        );

        $reconciler = $this->getReconciler();

        $reconciler->reconcile($card);

        /** @var Card $cardReturned2 */
        $cardReturned2 = $reconciler->reconcile($card2);

        /** @var Card $cardReturned */
        $cardReturned = $reconciler->reconcile($card);

        $this->assertInstanceOf(Card::class, $cardReturned);
        $this->assertEquals($cardReturned->brand, 'Visa');
        $this->assertEquals($cardReturned->merchant_account_id, 1234);

        $this->assertInstanceOf(Card::class, $cardReturned2);
        $this->assertEquals($cardReturned2->brand, 'Mastercard');
        $this->assertEquals($cardReturned2->merchant_account_id, 5234);
    }

    public function testReconcileCardTestGateway(): void
    {
        $card = new CardValueObject(
            customer: self::$customer,
            gateway: 'test',
            gatewayId: 'card_test_gateway',
            merchantAccount: null,
            chargeable: true,
            brand: 'Visa',
            funding: 'credit',
            last4: '1234',
            expMonth: 1,
            expYear: 2020,
            country: 'US',
        );

        $reconciler = $this->getReconciler();

        $card = $reconciler->reconcile($card);

        $this->assertInstanceOf(Card::class, $card);
        $expected = [
            'brand' => 'Visa',
            'chargeable' => true,
            'created_at' => $card->created_at,
            'customer_id' => self::$customer->id,
            'exp_month' => 1,
            'exp_year' => 2020,
            'failure_reason' => null,
            'funding' => 'credit',
            'gateway' => 'test',
            'gateway_customer' => null,
            'gateway_id' => 'card_test_gateway',
            'gateway_setup_intent' => null,
            'id' => $card->id(),
            'issuing_country' => 'US',
            'last4' => '1234',
            'merchant_account' => null,
            'object' => 'card',
            'receipt_email' => null,
            'updated_at' => $card->updated_at,
        ];
        $this->assertEquals($expected, $card->toArray());
    }

    public function testReconcileBankAccount(): void
    {
        $account = new BankAccountValueObject(
            customer: self::$customer,
            gateway: 'invoiced',
            gatewayId: 'ach_test',
            merchantAccount: new MerchantAccount(['id' => 12345]),
            chargeable: false,
            bankName: 'Wells Fargo',
            routingNumber: '1110000000',
            last4: '1234',
            currency: 'usd',
            country: 'US',
            accountHolderName: 'Test Name',
            accountHolderType: 'company',
            type: 'checking',
            verified: false,
        );

        $reconciler = $this->getReconciler();

        $account = $reconciler->reconcile($account);

        $this->assertInstanceOf(BankAccount::class, $account);
        $expected = [
            'account_holder_name' => 'Test Name',
            'account_holder_type' => 'company',
            'bank_name' => 'Wells Fargo',
            'chargeable' => false,
            'country' => 'US',
            'created_at' => $account->created_at,
            'currency' => 'usd',
            'customer_id' => self::$customer->id,
            'failure_reason' => null,
            'gateway' => 'invoiced',
            'gateway_customer' => null,
            'gateway_id' => 'ach_test',
            'gateway_setup_intent' => null,
            'id' => $account->id(),
            'last4' => '1234',
            'merchant_account' => 12345,
            'object' => 'bank_account',
            'receipt_email' => null,
            'routing_number' => '1110000000',
            'type' => 'checking',
            'updated_at' => $account->updated_at,
            'verified' => false,
        ];
        $this->assertEquals($expected, $account->toArray());
    }

    private function getReconciler(): PaymentSourceReconciler
    {
        $reconciler = new PaymentSourceReconciler();
        $reconciler->setStatsd(new StatsdClient());

        return $reconciler;
    }
}
