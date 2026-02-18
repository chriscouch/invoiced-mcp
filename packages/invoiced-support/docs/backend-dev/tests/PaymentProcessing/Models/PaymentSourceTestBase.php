<?php

namespace App\Tests\PaymentProcessing\Models;

use App\Core\Statsd\StatsdClient;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Gateways\MockGateway;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\PaymentProcessing\Interfaces\PaymentSourceVaultInterface;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Operations\DeletePaymentInfo;
use App\Tests\AppTestCase;
use Mockery;

abstract class PaymentSourceTestBase extends AppTestCase
{
    protected static PaymentSource $source;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    abstract public function getModel(): string;

    abstract public function expectedMethod(): string;

    abstract public function getCreateParams(): array;

    abstract public function expectedArray(): array;

    abstract public function editSource(): void;

    abstract public function expectedTypeName(): string;

    public function testEventAssociations(): void
    {
        $class = $this->getModel();
        /** @var PaymentSource $source */
        $source = new $class();
        $source->customer_id = 1234;

        $this->assertEquals([
            ['customer', 1234],
        ], $source->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $class = $this->getModel();
        /** @var PaymentSource $source */
        $source = new $class();
        $source->customer = self::$customer;

        $this->assertEquals(array_merge($source->toArray(), [
            'customer' => ModelNormalizer::toArray(self::$customer),
        ]), $source->getEventObject());
    }

    public function testGetMethod(): void
    {
        $class = $this->getModel();
        /** @var PaymentSource $source */
        $source = new $class();
        $this->assertEquals($this->expectedMethod(), $source->getMethod());
    }

    public function testGetPaymentMethod(): void
    {
        $class = $this->getModel();
        /** @var PaymentSource $source */
        $source = new $class();
        $source->tenant_id = (int) self::$company->id();
        $method = $source->getPaymentMethod();
        $this->assertInstanceOf(PaymentMethod::class, $method);
        $this->assertEquals($this->expectedMethod(), $method->id);
    }

    public function testGetPaymentSource(): void
    {
        $class = $this->getModel();
        /** @var PaymentSource $source */
        $source = new $class();
        $source->customer = self::$customer;
        $source->gateway = TestGateway::ID;
        $account = $source->getMerchantAccount();
        $this->assertInstanceOf(MerchantAccount::class, $account);
        $this->assertEquals(TestGateway::ID, $account->gateway);
    }

    public function testGetTypeName(): void
    {
        $class = $this->getModel();
        /** @var PaymentSource $source */
        $source = new $class();
        $this->assertEquals($this->expectedTypeName(), $source->getTypeName());
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        $this->assertNull(self::$customer->payment_source);

        $class = $this->getModel();
        /** @var PaymentSource $source */
        $source = new $class();
        self::$source = $source;

        $params = array_replace([
                'customer' => self::$customer,
                'gateway' => MockGateway::ID,
            ], $this->getCreateParams());
        $this->assertTrue(self::$source->create($params));

        $this->assertEquals(self::$customer->id(), self::$source->customer_id);

        $this->assertTrue(self::$customer->setDefaultPaymentSource(self::$source));

        /** @var PaymentSource $source */
        $source = self::$customer->payment_source;
        $this->assertInstanceOf($class, $source); /* @phpstan-ignore-line */
        $this->assertEquals(self::$source->id(), $source->id());
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        $this->assertHasEvent(self::$source, EventType::PaymentSourceCreated);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $this->assertEquals($this->expectedArray(), self::$source->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        EventSpool::enable();
        $this->editSource();
        $this->assertTrue(self::$source->save());
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        $this->assertHasEvent(self::$source, EventType::PaymentSourceUpdated);
    }

    /**
     * @depends testCreate
     */
    public function testIsDefault(): void
    {
        $this->assertTrue(self::$customer->clearDefaultPaymentSource());
        self::$source->customer->refresh();
        $this->assertFalse(self::$source->isDefault());

        $this->assertTrue(self::$customer->setDefaultPaymentSource(self::$source));
        self::$source->customer->refresh();
        $this->assertTrue(self::$source->isDefault());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        EventSpool::enable();

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('deleteSource')
            ->andReturn(true)
            ->once();
        $gatewayFactory = Mockery::mock(PaymentGatewayFactory::class);
        $gatewayFactory->shouldReceive('get')->andReturn($gateway);

        $gatewayLogger = self::getService('test.gateway_logger');
        $deletePaymentInfo = new DeletePaymentInfo($gatewayFactory, $gatewayLogger);
        $deletePaymentInfo->setStatsd(new StatsdClient());

        $deletePaymentInfo->delete(self::$source);

        $this->assertNull(self::$customer->refresh()->default_source_type);
        $this->assertNull(self::$customer->default_source_id);
        // should not actually delete the source
        $this->assertTrue(self::$source->persisted());
        $this->assertFalse(self::$source->chargeable);
    }

    /**
     * @depends testDelete
     */
    public function testEventDeleted(): void
    {
        $this->assertHasEvent(self::$source, EventType::PaymentSourceDeleted);
    }

    public function testDeleteFail(): void
    {
        // create a new source
        $params = array_replace([
            'gateway' => MockGateway::ID,
        ], $this->getCreateParams());
        $class = $this->getModel();
        /** @var PaymentSource $source */
        $source = new $class($params);
        $source->customer = self::$customer;
        self::$source = $source;
        self::$source->saveOrFail();

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('deleteSource')
            ->andThrow(new PaymentSourceException('fail'))
            ->once();
        $gatewayFactory = Mockery::mock(PaymentGatewayFactory::class);
        $gatewayFactory->shouldReceive('get')->andReturn($gateway);

        $gatewayLogger = self::getService('test.gateway_logger');
        $deletePaymentInfo = new DeletePaymentInfo($gatewayFactory, $gatewayLogger);
        $deletePaymentInfo->setStatsd(new StatsdClient());

        $deletePaymentInfo->delete(self::$source);

        $this->assertNull(self::$customer->refresh()->default_source_type);
        $this->assertNull(self::$customer->default_source_id);
        // should not actually delete the source
        $this->assertTrue(self::$source->persisted());
        $this->assertFalse(self::$source->chargeable);
    }
}
