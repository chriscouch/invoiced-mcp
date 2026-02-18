<?php

namespace App\Tests\PaymentProcessing;

use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\Cron\ValueObjects\Run;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\TenantContext;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\InfuseUtility as Utility;
use App\EntryPoint\CronJob\ReplayInitiatedCharge;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Interfaces\ChargeApplicationItemInterface;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\InitiatedCharge;
use App\PaymentProcessing\Models\InitiatedChargeDocument;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Operations\ProcessPayment;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\CreditNoteChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\EstimateChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\InvoiceChargeApplicationItem;
use App\Tests\AppTestCase;
use Doctrine\DBAL\Connection;
use Exception;
use Mockery\MockInterface;

class ReplayInitiatedChargeTest extends AppTestCase
{
    private static ProcessPayment|MockInterface $processPayment;
    private static ChargeApplication $estimateApplication;
    private static ChargeApplication $invoiceApplication;
    private static ReplayInitiatedCharge $class;
    private static InvoiceChargeApplicationItem $invoiceItem;
    private static EstimateChargeApplicationItem $estimateItem;

    public static function setUpBeforeClass(): void
    {
        InitiatedCharge::queryWithoutMultitenancyUnsafe()->delete();
        $tenantContext = new TenantContext(self::getService('test.event_spool'), self::getService('test.email_spool'));
        self::$processPayment = \Mockery::mock(ProcessPayment::class);
        self::$processPayment->shouldReceive('setMutexLock')->andReturn(true);

        self::$class = new class($tenantContext, self::$processPayment) extends ReplayInitiatedCharge {
            public function execute(Run $run): void
            {
                parent::execute($run);
            }
        };

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasEstimate();
        self::hasUnappliedCreditNote();
        self::hasCard();
        self::hasBankAccount();
        self::hasMerchantAccount('test');
        self::$estimate->deposit = 200;
        self::$estimate->saveOrFail();

        $money = Money::fromDecimal('usd', 100);
        self::$invoiceItem = new InvoiceChargeApplicationItem($money, self::$invoice);
        self::$invoiceApplication = new ChargeApplication([self::$invoiceItem], PaymentFlowSource::Charge);

        $money = Money::fromDecimal('usd', 200);
        self::$estimateItem = new EstimateChargeApplicationItem($money, self::$estimate);
        self::$estimateApplication = new ChargeApplication([self::$estimateItem], PaymentFlowSource::Charge);
    }

    public function testFailedCharges(): void
    {
        self::$processPayment->shouldNotHaveBeenCalled();
        $initiateCharge = new InitiatedCharge();
        $initiateCharge->charge = new \stdClass();
        $initiateCharge->amount = 0;
        $initiateCharge->correlation_id = 'test';
        $initiateCharge->currency = 'usd';
        $initiateCharge->gateway = 'invoiced';
        $initiateCharge->application_source = PaymentFlowSource::Charge->toString();
        $initiateCharge->source = null;
        $initiateCharge->customer = self::$customer;
        $initiateCharge->saveOrFail();

        // created at condition
        $initiateCharge = new InitiatedCharge();
        $initiateCharge->charge = (object) [
            'foo' => 'bar',
        ];
        $initiateCharge->amount = 0;
        $initiateCharge->correlation_id = 'test';
        $initiateCharge->currency = 'usd';
        $initiateCharge->gateway = 'invoiced';
        $initiateCharge->application_source = PaymentFlowSource::Charge->toString();
        $initiateCharge->source = null;
        $initiateCharge->customer = self::$customer;
        $initiateCharge->saveOrFail();
        $initiateChargeDocument = new InitiatedChargeDocument();
        $initiateChargeDocument->initiated_charge = $initiateCharge;
        $initiateChargeDocument->document_type = ObjectType::Invoice->value;
        $initiateChargeDocument->document_id = self::$invoice->id;
        $initiateChargeDocument->amount = 100;
        $initiateChargeDocument->saveOrFail();
        self::$class->execute(new Run());
        $this->assertTrue(true);
    }

    /**
     * @dataProvider objectProvider
     */
    public function testFailed(callable $cb): void
    {
        /** @var ChargeApplication $application */
        $application = $cb();
        $result = $this->getBankSource();
        $initiateCharge = $this->buildInitiatedCharge($result, 100, 'gocardless', $application->getItems());

        self::$processPayment->shouldReceive('handleFailedPayment')
            ->withArgs(function (ChargeException $e, PaymentMethod $arg2, ChargeApplication $arg3, InitiatedCharge $arg4, ?string $arg5) use ($initiateCharge, $application) {
                $arg1 = $e->charge;

                return 'gocardless' == $arg1?->gateway
                    && Charge::FAILED == $arg1?->status
                    && null === $arg1?->gatewayId
                    && PaymentMethod::DIRECT_DEBIT == $arg2->id
                    && 1 === count($application->getItems())
                    && 1 === count($arg3->getItems())
                    && $this->getDocumentFromApplication($application)->id === $this->getDocumentFromApplication($arg3)->id
                    && $initiateCharge->id === $arg4->id
                    && null === $arg5;
            })
            ->once();
        self::$class->execute(new Run());
        $initiateCharge->delete();
        $this->assertTrue(true);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testNoPaymentSurce(callable $cb, string $status): void
    {
        /** @var ChargeApplication $application */
        $application = $cb();
        $result = $this->getSource($status, 'invoiced');
        $result['source'] = null;
        $initiateCharge = $this->buildInitiatedCharge($result, 100, 'invoiced', $application->getItems());
        // missing payment source
        self::$class->execute(new Run());
        $initiateCharge->delete();
        $this->assertTrue(true);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testCard(callable $cb, string $status): void
    {
        /** @var ChargeApplication $application */
        $application = $cb();
        $result = $this->getSource($status, 'invoiced');
        $initiateCharge = $this->buildInitiatedCharge($result, 100, 'invoiced', $application->getItems());
        self::$processPayment->shouldReceive('handleSuccessfulPayment')
            ->withArgs(function (ChargeValueObject $arg1, PaymentMethod $arg2, ChargeApplication $arg3, InitiatedCharge $arg4, ?string $arg5) use ($initiateCharge, $status, $application) {
                return Money::fromDecimal('usd', 100)->equals($arg1->amount)
                    && 'invoiced' == $arg1->gateway
                    && $status == $arg1->status
                    && null == $arg1->source
                    && PaymentMethod::CREDIT_CARD == $arg2->id
                    && 1 === count($application->getItems())
                    && 1 === count($arg3->getItems())
                    && $this->getDocumentFromApplication($application)->id === $this->getDocumentFromApplication($arg3)->id
                    && $initiateCharge->id === $arg4->id
                    && null === $arg5;
            })
            ->once();
        self::$class->execute(new Run());
        $initiateCharge->delete();

        $result = $this->getCardSource($status, 'invoiced');
        $initiateCharge = $this->buildInitiatedCharge($result, 100, 'invoiced', $application->getItems());

        self::$processPayment->shouldReceive('handleSuccessfulPayment')
            ->withArgs(function (ChargeValueObject $arg1, PaymentMethod $arg2, ChargeApplication $arg3, InitiatedCharge $arg4, ?string $arg5) use ($application, $status) {
                return Money::fromDecimal('usd', 100) == $arg1->amount
                    && 'invoiced' == $arg1->gateway
                    && $status == $arg1->status
                    && null == $arg1->source?->id
                    && PaymentMethod::CREDIT_CARD == $arg2->id
                    && 1 === count($application->getItems())
                    && 1 === count($arg3->getItems())
                    && $this->getDocumentFromApplication($application)->id === $this->getDocumentFromApplication($arg3)->id
                    && null === $arg5;
            })
            ->twice();
        self::$class->execute(new Run());
        $initiateCharge->delete();
        $result = $this->getCardSource($status, 'invoiced', self::$card->gateway_id);
        $initiateCharge = $this->buildInitiatedCharge($result, 100, 'invoiced', $application->getItems());
        self::$class->execute(new Run());
        $initiateCharge->delete();
        $this->assertTrue(true);
    }

    public function testWithConvenienceFee(): void
    {
        $paymentMethod = PaymentMethod::findOrFail([self::$company->id(), PaymentMethod::CREDIT_CARD]);
        $paymentMethod->convenience_fee = 400;
        $paymentMethod->saveOrFail();

        $money = Money::fromDecimal('usd', 50);
        $invoiceItem = new InvoiceChargeApplicationItem($money, self::$invoice);
        $money = Money::fromDecimal('usd', 150);
        $estimateItem = new EstimateChargeApplicationItem($money, self::$estimate);

        $money = Money::fromDecimal('usd', 50);
        $item = new CreditNoteChargeApplicationItem($money, self::$creditNote, self::$invoice);
        $application = new ChargeApplication([$invoiceItem, $estimateItem, $item], PaymentFlowSource::Charge);

        $status = Charge::SUCCEEDED;
        $result = $this->getSource($status, 'invoiced', 20800);
        // test inconsistent amount
        $initiateCharge = $this->buildInitiatedCharge($result, 104, 'invoiced', $application->getItems());
        self::$class->execute(new Run());
        $initiateCharge->delete();

        $initiateCharge = $this->buildInitiatedCharge($result, 208, 'invoiced', $application->getItems());
        self::$processPayment->shouldReceive('handleSuccessfulPayment')
            ->withArgs(function (ChargeValueObject $arg1, PaymentMethod $arg2, ChargeApplication $arg3, InitiatedCharge $arg4, ?string $arg5) use ($initiateCharge, $status, $application) {
                return Money::fromDecimal('usd', 208) == $arg1->amount
                    && 'invoiced' == $arg1->gateway
                    && $status == $arg1->status
                    && null == $arg1->source
                    && PaymentMethod::CREDIT_CARD == $arg2->id
                    && 200.0 === $application->getPaymentAmount()->toDecimal()
                    && 208.0 === $arg3->getPaymentAmount()->toDecimal()
                    && 4 === count($arg3->getItems())
                    && $this->getDocumentFromApplication($application)->id === $this->getDocumentFromApplication($arg3)->id
                    && $initiateCharge->id === $arg4->id
                    && null === $arg5;
            })
            ->once();

        self::$class->execute(new Run());
        $initiateCharge->delete();
        $paymentMethod->convenience_fee = 0;
        $paymentMethod->saveOrFail();
        $this->assertTrue(true);
    }

    /**
     * @dataProvider bankProvider
     */
    public function testBank(callable $cb, string $status, string $method): void
    {
        /** @var ChargeApplication $application */
        $application = $cb();
        $gateway = PaymentMethod::DIRECT_DEBIT === $method ? 'gocardless' : 'invoiced';
        $result = $this->getBankSource($status, $gateway);
        $initiateCharge = $this->buildInitiatedCharge($result, 100, $gateway, $application->getItems());
        self::$processPayment->shouldReceive('handleSuccessfulPayment')
            ->withArgs(function (ChargeValueObject $arg1, PaymentMethod $arg2, ChargeApplication $arg3, InitiatedCharge $arg4, ?string $arg5) use ($initiateCharge, $application, $method, $status, $gateway) {
                return Money::fromDecimal('usd', 100)->equals($arg1->amount)
                    && $gateway == $arg1->gateway
                    && $status == $arg1->status
                    && $method == $arg2->id
                    && 1 === count($application->getItems())
                    && 1 === count($arg3->getItems())
                    && $this->getDocumentFromApplication($application)->id === $this->getDocumentFromApplication($arg3)->id
                    && $initiateCharge->id === $arg4->id
                    && null === $arg5;
            })
            ->once();
        self::$class->execute(new Run());
        $initiateCharge->delete();
        $result = $this->getBankSource($status, $gateway, null, self::$bankAccount);
        $initiateCharge = $this->buildInitiatedCharge($result, 100, $gateway, $application->getItems());

        self::$processPayment->shouldReceive('handleSuccessfulPayment')
            ->withArgs(function (ChargeValueObject $arg1, PaymentMethod $arg2, ChargeApplication $arg3, InitiatedCharge $arg4, ?string $arg5) use ($application, $status, $method, $gateway) {
                return Money::fromDecimal('usd', 100) == $arg1->amount
                    && $gateway == $arg1->gateway
                    && $status == $arg1->status
                    && null !== $arg1->source?->id
                    && $method == $arg2->id
                    && 1 === count($application->getItems())
                    && 1 === count($arg3->getItems())
                    && $this->getDocumentFromApplication($application)->id === $this->getDocumentFromApplication($arg3)->id
                    && null === $arg5;
            })
            ->twice();
        self::$class->execute(new Run());
        $initiateCharge->delete();

        $result = $this->getBankSource($status, $gateway, self::$bankAccount->gateway_id);
        $initiateCharge = $this->buildInitiatedCharge($result, 100, $gateway, $application->getItems());
        self::$class->execute(new Run());
        $initiateCharge->delete();
        $this->assertTrue(true);
    }

    /**
     * @param ChargeApplicationItemInterface[] $objects
     */
    private function buildInitiatedCharge(array $testInput, int $amount, string $gateway, array $objects, ?PaymentSource $source = null): InitiatedCharge
    {
        $initiateCharge = new InitiatedCharge();
        $initiateCharge->charge = (object) $testInput;
        $initiateCharge->amount = $amount;
        $initiateCharge->correlation_id = 'test';
        $initiateCharge->currency = 'usd';
        $initiateCharge->gateway = $gateway;
        $initiateCharge->application_source = PaymentFlowSource::Charge->toString();
        $initiateCharge->source = $source;
        $initiateCharge->customer = self::$customer;
        $initiateCharge->saveOrFail();

        /** @var Connection $connection */
        $connection = self::getService('test.database');
        $connection->update('InitiatedCharges', ['created_at' => Utility::unixToDb(time() - 601)], ['id' => $initiateCharge->id]);

        foreach ($objects as $object) {
            $doc = $object->getDocument();
            if (!$doc) {
                continue;
            }
            $initiateChargeDocument = new InitiatedChargeDocument();
            $initiateChargeDocument->initiated_charge = $initiateCharge;
            $initiateChargeDocument->document_type = ObjectType::fromModel($doc)->value;
            $initiateChargeDocument->document_id = $doc->id;
            $initiateChargeDocument->amount = ($object instanceof CreditNoteChargeApplicationItem ? $object->getCredit() : $object->getAmount())->toDecimal();
            $initiateChargeDocument->saveOrFail();
        }

        return $initiateCharge;
    }

    public function objectProvider(): array
    {
        return [
            [fn () => self::$invoiceApplication],
            [fn () => self::$estimateApplication],
        ];
    }

    public function dataProvider(): array
    {
        return [
            [fn () => self::$invoiceApplication, Charge::SUCCEEDED],
            [fn () => self::$invoiceApplication, Charge::PENDING],
            [fn () => self::$estimateApplication, Charge::SUCCEEDED],
        ];
    }

    public function bankProvider(): array
    {
        return [
            [fn () => self::$invoiceApplication, Charge::SUCCEEDED, PaymentMethod::DIRECT_DEBIT],
            [fn () => self::$invoiceApplication, Charge::PENDING, PaymentMethod::ACH],
        ];
    }

    private function getDocumentFromApplication(ChargeApplication $app): ReceivableDocument
    {
        return $app->getItems()[0]->getDocument() ?? throw new Exception('should not be null');
    }

    private function getSource(string $status = Charge::FAILED, string $gateway = 'gocardless', int $amount = 10000): array
    {
        return [
            'status' => $status,
            'timestamp' => time(),
            'gateway' => $gateway,
            'currency' => 'usd',
            'amount' => $amount,
            'method' => null,
            'id' => null,
            'message' => Charge::FAILED === $status ? 'test' : null,
            'source' => [
                'object' => 'card',
                'brand' => 'brand',
                'last4' => 'last4',
                'exp_month' => 2,
                'exp_year' => 2022,
                'funding' => 'funding',
                'issuing_country' => 'US',
                'id' => null,
                'customer' => null,
            ],
        ];
    }

    private function getCardSource(string $status = Charge::FAILED, string $gateway = 'gocardless', ?string $gatewayId = null): array
    {
        $source = $this->getSource($status, $gateway);
        $source['id'] = $gatewayId;
        $source['source']['id'] = $gatewayId;
        $source['source']['brand'] = self::$card->brand;
        $source['source']['last4'] = self::$card->last4;
        $source['source']['exp_month'] = self::$card->exp_month;
        $source['source']['exp_year'] = self::$card->exp_year;

        return $source;
    }

    private function getBankSource(string $status = Charge::FAILED, string $gateway = 'gocardless', ?string $gatewayId = null, BankAccount $bankAccount = null): array
    {
        $source = $this->getSource($status, $gateway);
        $source['source']['object'] = 'bank_account';
        $source['source']['verified'] = $bankAccount ? $bankAccount->verified : false;
        $source['id'] = $gatewayId;
        $source['source']['id'] = $gatewayId;
        $source['source']['bank_name'] = $bankAccount ? $bankAccount->bank_name : 'bank_name';
        $source['source']['last4'] = $bankAccount ? $bankAccount->last4 : 'last4';
        $source['source']['routing_number'] = $bankAccount ? $bankAccount->routing_number : 'routing_number';
        $source['source']['currency'] = $bankAccount ? $bankAccount->currency : 'usd';
        $source['source']['country'] = $bankAccount ? $bankAccount->country : 'US';

        return $source;
    }
}
