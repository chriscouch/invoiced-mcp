<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Enums\PaymentLinkStatus;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Models\PaymentLinkField;
use App\AccountsReceivable\Models\PaymentLinkItem;
use App\AccountsReceivable\Models\PaymentLinkSession;
use App\Core\Orm\Model;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Enums\EventType;
use App\Tests\ModelTestCase;
use Carbon\CarbonImmutable;

/**
 * @extends ModelTestCase<PaymentLink>
 */
class PaymentLinkTest extends ModelTestCase
{
    private static PaymentLink $paymentLink;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCustomer();
    }

    protected function getModelCreate(): Model
    {
        $paymentLink = new PaymentLink();
        $paymentLink->status = PaymentLinkStatus::Active;
        $paymentLink->reusable = true;
        $paymentLink->currency = 'usd';
        self::$paymentLink = $paymentLink;

        return $paymentLink;
    }

    protected function getExpectedToArray($model, array &$output): array
    {
        return [
            'after_completion_url' => null,
            'collect_billing_address' => null,
            'collect_phone_number' => null,
            'collect_shipping_address' => null,
            'created_at' => self::$paymentLink->created_at,
            'currency' => 'usd',
            'customer_id' => null,
            'deleted' => null,
            'deleted_at' => null,
            'id' => self::$paymentLink->id,
            'name' => 'Payment Link',
            'object' => 'payment_link',
            'reusable' => true,
            'status' => 'active',
            'terms_of_service_url' => null,
            'updated_at' => self::$paymentLink->updated_at,
            'url' => self::$company->url.'/pay/'.self::$paymentLink->client_id,
        ];
    }

    protected function getModelEdit($model): PaymentLink
    {
        $model->reusable = false;

        return $model;
    }

    public function testCreate(): void
    {
        parent::testCreate();
        $this->assertEquals(self::$company->id(), self::$paymentLink->tenant_id);

        $item1 = new PaymentLinkItem();
        $item1->payment_link = self::$paymentLink;
        $item1->description = 'Item 1';
        $item1->amount = 100;
        $item1->saveOrFail();

        $item2 = new PaymentLinkItem();
        $item2->payment_link = self::$paymentLink;
        $item2->description = 'Item 2';
        $item2->amount = 200;
        $item2->saveOrFail();

        $field = new PaymentLinkField();
        $field->payment_link = self::$paymentLink;
        $field->object_type = ObjectType::Customer;
        $field->custom_field_id = 'test';
        $field->required = true;
        $field->order = 1;
        $field->saveOrFail();

        $session = new PaymentLinkSession();
        $session->payment_link = self::$paymentLink;
        $session->completed_at = CarbonImmutable::now();
        $session->saveOrFail();
    }

    public function testEventAssociations(): void
    {
        $paymentLink = new PaymentLink();
        $paymentLink->customer = new Customer(['id' => 1234]);

        $this->assertEquals([
            ['customer', 1234],
        ], $paymentLink->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $paymentLink = new PaymentLink();
        $paymentLink->customer = self::$customer;

        $this->assertEquals(array_merge($paymentLink->toArray(), [
            'customer' => ModelNormalizer::toArray(self::$customer),
            'items' => [],
        ]), $paymentLink->getEventObject());
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        $this->assertHasEvent(self::$paymentLink, EventType::PaymentLinkCreated);
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        $this->assertHasEvent(self::$paymentLink, EventType::PaymentLinkUpdated);
    }

    /**
     * @depends testDelete
     */
    public function testEventDeleted(): void
    {
        $this->assertHasEvent(self::$paymentLink, EventType::PaymentLinkDeleted);
    }
}
