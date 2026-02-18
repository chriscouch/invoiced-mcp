<?php

namespace App\Tests\PaymentProcessing\Forms;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Statsd\StatsdClient;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Forms\PaymentInfoFormBuilder;
use App\PaymentProcessing\Forms\PaymentInfoFormProcessor;
use App\PaymentProcessing\Gateways\MockGateway;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\PaymentProcessing\Interfaces\PaymentSourceVaultInterface;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Operations\DeletePaymentInfo;
use App\PaymentProcessing\Operations\VaultPaymentInfo;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentFormSettings;
use App\Tests\AppTestCase;
use Mockery;

class PaymentInfoFormProcessorTest extends AppTestCase
{
    private static PaymentSource $originalCard;
    private static PaymentMethod $paymentMethod;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::acceptsCreditCards();
        self::hasCustomer();
        self::hasInvoice();

        self::$paymentMethod = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
    }

    private function getFormBuilder(?PaymentFormSettings $settings = null): PaymentInfoFormBuilder
    {
        $settings ??= new PaymentFormSettings(
            self::$company,
            false,
            false,
            false,
            false
        );

        return new PaymentInfoFormBuilder($settings);
    }

    private function getFormProcessor(PaymentGatewayInterface $gateway): PaymentInfoFormProcessor
    {
        $gatewayFactory = Mockery::mock(PaymentGatewayFactory::class);
        $gatewayFactory->shouldReceive('get')->andReturn($gateway);
        $reconciler = new PaymentSourceReconciler();
        $reconciler->setStatsd(new StatsdClient());
        $gatewayLogger = self::getService('test.gateway_logger');
        $deletePaymentInfo = new DeletePaymentInfo($gatewayFactory, $gatewayLogger);
        $deletePaymentInfo->setStatsd(new StatsdClient());
        $vaultPaymentInfo = new VaultPaymentInfo($reconciler, $gatewayFactory, $deletePaymentInfo, $gatewayLogger);
        $vaultPaymentInfo->setStatsd(new StatsdClient());
        $events = new CustomerPortalEvents(self::getService('test.database'));
        $processPayment = self::getService('test.process_payment');
        $processPayment->setGatewayFactory($gatewayFactory);
        $autoPay = self::getService('test.autopay');
        $autoPay->setProcessPayment($processPayment);

        $form = new PaymentInfoFormProcessor($vaultPaymentInfo, $autoPay, $events);
        $form->setStatsd(new StatsdClient());

        return $form;
    }

    public function testHandleSubmit(): void
    {
        $parameters = ['test' => true];

        $card = new CardValueObject(
            customer: self::$customer,
            gateway: MockGateway::ID,
            gatewayId: uniqid(),
            chargeable: true,
            brand: 'Visa',
            funding: 'unknown',
            last4: '1234',
            expMonth: 2,
            expYear: 2020,
        );

        $account = self::$paymentMethod->getDefaultMerchantAccount();

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('vaultSource')
            ->withArgs([self::$customer, $account, $parameters])
            ->andReturn($card)
            ->once();

        $processor = $this->getFormProcessor($gateway);
        $builder = $this->getFormBuilder();
        $builder->setCustomer(self::$customer);
        $builder->setMethod(self::$paymentMethod);

        $result = $processor->handleSubmit($builder->build(), $parameters);

        $this->assertInstanceOf(PaymentSource::class, $result);
        $this->assertEquals($card->gateway, $result->gateway);
        $this->assertEquals($card->gatewayId, $result->gateway_id);
        $this->assertTrue($result->chargeable);
        $this->assertEquals($result, self::$customer->payment_source);
        self::$originalCard = $result;
    }

    public function testHandleSubmitFail(): void
    {
        $this->expectException(FormException::class);
        $this->expectExceptionMessage('error');

        $customer = new Customer();
        $parameters = ['test' => true];

        $account = self::$paymentMethod->getDefaultMerchantAccount();

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('vaultSource')
            ->withArgs([$customer, $account, $parameters])
            ->andThrow(new PaymentSourceException('error'));

        $processor = $this->getFormProcessor($gateway);
        $builder = $this->getFormBuilder();
        $builder->setCustomer($customer);
        $builder->setMethod(self::$paymentMethod);

        $processor->handleSubmit($builder->build(), $parameters);
    }

    /**
     * @depends testHandleSubmit
     */
    public function testHandleSubmitExistingCard(): void
    {
        $parameters = ['test' => true];

        $card = new CardValueObject(
            customer: self::$customer,
            gateway: MockGateway::ID,
            gatewayId: uniqid(),
            chargeable: true,
            brand: 'Visa',
            funding: 'unknown',
            last4: '1234',
            expMonth: 2,
            expYear: 2020,
        );

        $account = self::$paymentMethod->getDefaultMerchantAccount();

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('vaultSource')
            ->withArgs([self::$customer, $account, $parameters])
            ->andReturn($card)
            ->once();
        $gateway->shouldReceive('deleteSource');

        $processor = $this->getFormProcessor($gateway);
        $builder = $this->getFormBuilder();
        $builder->setCustomer(self::$customer);
        $builder->setMethod(self::$paymentMethod);

        $result = $processor->handleSubmit($builder->build(), $parameters);

        $this->assertInstanceOf(PaymentSource::class, $result);
        $this->assertEquals($card->gateway, $result->gateway);
        $this->assertEquals($card->gatewayId, $result->gateway_id);
        $this->assertTrue($result->chargeable);
        $this->assertEquals($result, self::$customer->payment_source);

        $this->assertFalse(self::$originalCard->refresh()->chargeable);
    }

    /**
     * @depends testHandleSubmit
     */
    public function testHandleSubmitExistingCardNoReplace(): void
    {
        $originalSource = self::$customer->payment_source;

        $parameters = [
            'test' => true,
            'make_default' => false,
        ];

        $card = new CardValueObject(
            customer: self::$customer,
            gateway: MockGateway::ID,
            gatewayId: uniqid(),
            chargeable: true,
            brand: 'Mastercard',
            funding: 'unknown',
            last4: '5454',
            expMonth: 3,
            expYear: 2023,
        );

        $account = self::$paymentMethod->getDefaultMerchantAccount();

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('vaultSource')
            ->withArgs([self::$customer, $account, ['test' => true]])
            ->andReturn($card)
            ->once();
        $gateway->shouldReceive('deleteSource');

        $processor = $this->getFormProcessor($gateway);
        $builder = $this->getFormBuilder();
        $builder->setCustomer(self::$customer);
        $builder->setMethod(self::$paymentMethod);

        $result = $processor->handleSubmit($builder->build(), $parameters);

        $this->assertInstanceOf(PaymentSource::class, $result);
        $this->assertEquals($card->gateway, $result->gateway);
        $this->assertEquals($card->gatewayId, $result->gateway_id);
        $this->assertTrue($result->chargeable);
        $this->assertEquals($originalSource, self::$customer->payment_source);

        $this->assertFalse(self::$originalCard->refresh()->chargeable);
    }

    public function testHandleSubmitPayOutstanding(): void
    {
        self::$invoice->autopay = true;
        self::$invoice->next_payment_attempt = strtotime('-1 hour');
        self::$invoice->saveOrFail();
        $this->assertFalse(self::$invoice->paid);

        $parameters = ['test' => true];

        $card = new CardValueObject(
            customer: self::$customer,
            gateway: MockGateway::ID,
            gatewayId: uniqid(),
            chargeable: true,
            brand: 'Visa',
            funding: 'unknown',
            last4: '1234',
            expMonth: 2,
            expYear: 2020,
        );

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('vaultSource')
            ->andReturn($card)
            ->once();

        $charge = new ChargeValueObject(
            customer: self::$customer,
            amount: Money::fromDecimal('usd', self::$invoice->balance),
            gateway: 'mock',
            gatewayId: uniqid(),
            method: PaymentMethod::CREDIT_CARD,
            status: Charge::SUCCEEDED,
            merchantAccount: null,
            source: null,
            description: '',
        );

        $gateway->shouldReceive('chargeSource')
            ->andReturn($charge)
            ->once();
        $gateway->shouldReceive('deleteSource');

        $processor = $this->getFormProcessor($gateway);
        $builder = $this->getFormBuilder();
        $builder->setCustomer(self::$customer);
        $builder->setMethod(self::$paymentMethod);

        $processor->handleSubmit($builder->build(), $parameters);

        $this->assertTrue(self::$invoice->refresh()->paid);
    }

    public function testHandleSubmitAutoPay(): void
    {
        $parameters = ['test' => true, 'enroll_autopay' => true];

        $card = new CardValueObject(
            customer: self::$customer,
            gateway: MockGateway::ID,
            gatewayId: uniqid(),
            chargeable: true,
            brand: 'Visa',
            funding: 'unknown',
            last4: '1234',
            expMonth: 2,
            expYear: 2020,
        );

        $account = self::$paymentMethod->getDefaultMerchantAccount();

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('vaultSource')
            ->withArgs([self::$customer, $account, ['test' => true]])
            ->andReturn($card)
            ->once();
        $gateway->shouldReceive('deleteSource');

        $processor = $this->getFormProcessor($gateway);
        $builder = $this->getFormBuilder();
        $builder->setCustomer(self::$customer);
        $builder->setMethod(self::$paymentMethod);

        $result = $processor->handleSubmit($builder->build(), $parameters);

        $this->assertInstanceOf(PaymentSource::class, $result);
        $this->assertEquals($card->gateway, $result->gateway);
        $this->assertEquals($card->gatewayId, $result->gateway_id);
        $this->assertTrue($result->chargeable);
        $this->assertEquals($result->toArray(), self::$customer->payment_source->toArray()); /* @phpstan-ignore-line */
        self::$originalCard = $result;

        $this->assertTrue(self::$customer->autopay);
    }
}
