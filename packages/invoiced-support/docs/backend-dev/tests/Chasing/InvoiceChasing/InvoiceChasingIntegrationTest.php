<?php

namespace App\Tests\Chasing\InvoiceChasing;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\InvoiceDelivery;
use App\CashApplication\Models\Transaction;
use App\Chasing\InvoiceChasing\InvoiceChaseStateCalculator;
use App\EntryPoint\QueueJob\PerformScheduledSendsJob;
use App\EntryPoint\QueueJob\ScheduleInvoiceChaseSends;
use App\Sending\Models\ScheduledSend;
use App\Tests\AppTestCase;
use App\Tests\IntegrationTestTrait;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\Yaml\Yaml;

class InvoiceChasingIntegrationTest extends AppTestCase
{
    use IntegrationTestTrait;

    /**
     * Array of properties which if found in the test case data will
     * be interpreted if necessary and not necessarily their exact value.
     *
     * E.g. date: +0 +1 indicates that the date field should be the current time
     * plus one our.
     */
    private static array $dateProps = [
        'date',
        'due_date',
        'send_after',
    ];

    private static ScheduleInvoiceChaseSends $scheduleSendsJob;
    private static PerformScheduledSendsJob $performSendsJob;

    // test environment
    private string $testName;
    private bool $debugMode;
    private ?Invoice $invoice_ = null;
    private ?Invoice $invoice2 = null;
    private ?InvoiceDelivery $delivery = null;
    private ?InvoiceDelivery $delivery2 = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();

        // enable invoice chasing
        self::$company->features->enable('smart_chasing');
        self::$company->features->enable('invoice_chasing');

        // create queue job instances
        self::$scheduleSendsJob = self::getService('test.schedule_invoice_chasing_job');
        self::$performSendsJob = self::getService('test.scheduled_sends_job');
    }

    /**
     * Executes the tests for each integration test case.
     */
    public function test(): void
    {
        // get test cases
        $cases = [];
        foreach ((array) glob(__DIR__.'/test_cases/*.yaml') as $filename) {
            $cases[] = (string) $filename;
        }
        sort($cases);

        // test each case
        foreach ($cases as $filename) {
            try {
                $this->initializeTestCaseEnvironment();
                $this->performTestCase($filename);
            } catch (\Throwable $e) {
                if ($e instanceof \PHPUnit\Framework\Exception) {
                    throw $e;
                }
                throw new \Exception('Uncaught error when processing "'.$filename.'" test case', $e->getCode(), $e);
            }
        }
    }

    public function testWithPendingTransaction(): void
    {
        self::hasInvoice();
        $delivery = new InvoiceDelivery();
        $delivery->invoice = self::$invoice;
        $delivery->saveOrFail();
        $this->assertFalse($delivery->refresh()->processed);

        $scheduller = new ScheduleInvoiceChaseSends(
            self::getService('test.tenant'),
            self::getService('test.database'),
            self::getService('test.transaction_manager'),
        );

        $scheduller->perform();
        $this->assertTrue($delivery->refresh()->processed);

        $delivery->processed = false;
        $delivery->saveOrFail();

        $transaction = new Transaction();
        $transaction->type = Transaction::TYPE_PAYMENT;
        $transaction->setInvoice(self::$invoice);
        $transaction->setCustomer(self::$customer);
        $transaction->amount = self::$invoice->balance;
        $transaction->status = Transaction::STATUS_PENDING;
        $transaction->saveOrFail();

        $scheduller->perform();
        $this->assertFalse($delivery->refresh()->processed);
    }

    /**
     * Clears the static variables used to create a test environment to run each test case.
     */
    private function initializeTestCaseEnvironment(): void
    {
        // reset the (possibly) mocked time
        CarbonImmutable::setTestNow();

        if ($this->invoice_) {
            // this will delete the delivery as well
            $this->invoice_->delete();
        }

        if ($this->invoice2) {
            // this will delete the delivery as well
            $this->invoice2->delete();
        }

        $this->invoice_ = null;
        $this->invoice2 = null;
        $this->delivery = null;
        $this->delivery2 = null;
    }

    /**
     * Performs test case file.
     */
    private function performTestCase(string $filename): void
    {
        // Parse the YAML definition
        $definition = Yaml::parseFile($filename);
        $fileParts = explode('/', $filename);
        $this->testName = explode('.', end($fileParts))[0];
        $this->debugMode = $definition['debug'] ?? false;
        if ($this->debugMode) {
            echo "\n\n---------------\n";
            echo "{$this->testName}\nDefinition:\n".json_encode($definition, JSON_PRETTY_PRINT);
            printf("\n");
        }

        foreach ($definition['operations'] as $key => $op) {
            // mock time
            if (isset($op['mock_time'])) {
                $this->mockTime($op['mock_time']);
            }

            $opName = $op['operation'];
            if ($this->debugMode) {
                printf("PERFORMING OPERATION (%d) '%s'\n", (int) $key, $opName);
            }

            switch ($opName) {
                case 'create':
                    $this->performCreate($op);
                    break;
                case 'edit':
                    $this->performEdit($op);
                    break;
                case 'delete':
                    $this->performDelete();
                    break;
                case 'schedule':
                    $this->performSchedule();
                    break;
                case 'send':
                    $this->performSend();
                    break;
            }

            // Refresh models.
            // Models need to be refreshed because changes to the models
            // that occur during processing may directly affect the other
            // models' data through a different instance.
            //
            // E.g. Updates to the invoice status cause the invoice model
            // to look up an associated InvoiceDelivery and may or may not
            // modify it so that it to be re-processed. In this case, a different
            // instance was modified
            if ($this->invoice_ instanceof Invoice) {
                $this->invoice_->refresh();
            }
            if ($this->delivery instanceof InvoiceDelivery) {
                $this->delivery->refresh();
            }

            $this->assertExpectations($op['expect'] ?? [], $this->buildBaseAssertionMessage($filename, "$key - $opName"));
            $this->mockTime(); // reset time after operation
        }
    }

    //
    // Test Operations
    //

    /**
     * Performs the create operation defined in the test case.
     */
    private function performCreate(array $op): void
    {
        if ($this->invoice_ instanceof Invoice) {
            throw new ExpectationFailedException('Create operation already called. Use "edit" instead.');
        }

        $data = $op['data'] ?? [];
        // create invoice and delivery
        $this->invoice_ = $this->setInvoice(self::$customer, $data['invoice'] ?? []);
        if ($this->debugMode) {
            echo "\n\nCreated:\n".$this->outputModel($this->invoice_);
            printf("\n");
        }

        $this->delivery = $this->setDelivery($this->invoice_, $data['delivery'] ?? []);
        if ($this->debugMode) {
            echo "\n\nCreated:\n".$this->outputModel($this->delivery);
            printf("\n");
        }

        // create second invoice
        if (isset($data['invoice2'])) {
            $this->invoice2 = $this->setInvoice(self::$customer, $data['invoice2'] ?? []);
            if ($this->debugMode) {
                echo "\n\nCreated:\n".$this->outputModel($this->invoice2);
                printf("\n");
            }

            // create second invoice delivery
            if (isset($data['delivery2'])) {
                $this->delivery2 = $this->setDelivery($this->invoice2, $data['delivery2'] ?? []);
                if ($this->debugMode) {
                    echo "\n\nCreated:\n".$this->outputModel($this->delivery2);
                    printf("\n");
                }
            }
        }
    }

    /**
     * Performs the edit operation defined in the test case.
     */
    private function performEdit(array $op): void
    {
        if (!$this->invoice_ || !$this->delivery) {
            throw new ExpectationFailedException('Nothing to edit. Call create prior to edit.');
        }

        // update invoice and delivery
        $data = $op['data'] ?? [];
        if (isset($data['invoice'])) {
            $this->setInvoice(self::$customer, $data['invoice'], $this->invoice_);
        }
        if (isset($data['delivery'])) {
            $this->setDelivery($this->invoice_, $data['delivery'], $this->delivery);
        }
        if (isset($data['invoice2'])) {
            $this->setInvoice(self::$customer, $data['invoice2'], $this->invoice2);
        }
        if (isset($data['delivery2'])) {
            /* @phpstan-ignore-next-line */
            $this->setDelivery($this->invoice2, $data['delivery2'], $this->delivery2);
        }
    }

    /**
     * Performs the delete operation defined in the test case.
     */
    private function performDelete(): void
    {
        if ($this->delivery instanceof InvoiceDelivery) {
            $this->delivery->chase_schedule = [];
            $this->delivery->saveOrFail();
        }
    }

    /**
     * Performs the schedule operation defined in the test case.
     */
    private function performSchedule(): void
    {
        $jobArgs = [
            'tenant_id' => self::$company->id(),
        ];

        self::$scheduleSendsJob->args = $jobArgs;
        self::$scheduleSendsJob->perform();
    }

    /**
     * Performs the send operation defined in the test case.
     */
    private function performSend(): void
    {
        $jobArgs = [
            'tenant_id' => self::$company->id(),
        ];

        self::$performSendsJob->args = $jobArgs;
        self::$performSendsJob->perform();
    }

    //
    // Assertions
    //

    /**
     * Performs assertions against the test expectations.
     */
    private function assertExpectations(array $expectations, string $message): void
    {
        if (isset($expectations['invoice'])) {
            $this->assertArrayExpectations('invoice', [$expectations['invoice']], [$this->invoice_], $message);
        }
        if (isset($expectations['invoice2'])) {
            $this->assertArrayExpectations('invoice2', [$expectations['invoice2']], [$this->invoice2], $message);
        }
        if (isset($expectations['delivery'])) {
            $this->assertArrayExpectations('delivery', [$expectations['delivery']], [$this->delivery], $message);
        }
        if (isset($expectations['delivery2'])) {
            $this->assertArrayExpectations('delivery2', [$expectations['delivery2']], [$this->delivery2], $message);
        }
        if (isset($expectations['delivery_state'])) {
            /* @phpstan-ignore-next-line */
            $this->assertArrayExpectations('delivery_state', $expectations['delivery_state'], InvoiceChaseStateCalculator::getState($this->delivery), $message);
        }
        if (isset($expectations['scheduled_sends'])) {
            $scheduledSends = ScheduledSend::where('tenant_id', self::$company->id())
                ->sort('send_after ASC')
                ->all()
                ->toArray();
            $this->assertArrayExpectations('scheduled_sends', $expectations['scheduled_sends'], $scheduledSends, $message);
        }
    }

    /**
     * Asserts expectations on an array of values.
     */
    private function assertArrayExpectations(string $object, array $expectations, array $actuals, string $message): void
    {
        if ($this->debugMode) {
            printf("OBJECT ASSERTION '$object'\n");
        }

        $expectedCount = count($expectations);
        $actualCount = count($actuals);
        if ($expectedCount != $actualCount) {
            throw new ExpectationFailedException($message.": Number of expected array values ($expectedCount) does not match the actual number of array values ($actualCount).");
        }

        $i = 0;
        foreach ($expectations as $expected) {
            if ($this->debugMode) {
                printf("INDEX ASSERTION (%d)\n", $i);
            }

            $actual = $actuals[$i++];
            $keys = $this->getArrayKeys($expected);
            foreach ($keys as $key) {
                if ($this->debugMode) {
                    $expectedValue = var_export($this->getNestedKeyValue($key, $expected), true);
                    $actualValue = var_export($this->getNestedKeyValue($key, $actual), true);
                    printf("Asserting '$key' (e: $expectedValue, a: $actualValue)\n");
                }

                $parts = explode('.', $key);
                if (in_array($parts[count($parts) - 1], self::$dateProps)) {
                    // special assertion for date values
                    $expectedDate = $this->getTimeFromFormat($this->getNestedKeyValue($key, $expected));
                    $actualDate = $this->getTimeFromFormat($this->getNestedKeyValue($key, $actual));
                    $this->assertEquals($expectedDate->toIso8601String(), $actualDate->toIso8601String(), $message.' - key: '.$key);
                } else {
                    $this->assertArrayKeyEquals($key, $expected, $actual, $message.' - key: '.$key);
                }
            }
        }
    }

    //
    // Helpers
    //

    private function setInvoice(Customer $customer, array $data, ?Invoice $invoice = null): Invoice
    {
        // use date from custom format
        foreach (['date', 'due_date'] as $dateProp) {
            if (isset($data[$dateProp])) {
                $data[$dateProp] = $this->getTimeFromFormat($data[$dateProp])->unix();
            }
        }

        $invoice = $invoice ?? new Invoice();
        $invoice->setCustomer($customer);
        foreach ($data as $key => $value) {
            $invoice->$key = $value;
        }

        $invoice->saveOrFail();

        return $invoice;
    }

    private function setDelivery(Invoice $invoice, array $data, ?InvoiceDelivery $delivery = null): InvoiceDelivery
    {
        // re-format specific properties
        if (isset($data['chase_schedule'])) {
            foreach ($data['chase_schedule'] as &$step) {
                if (isset($step['options']['date'])) {
                    $step['options']['date'] = $this->getTimeFromFormat($step['options']['date'])->toIso8601String();
                }

                if (isset($step['id']) && $this->delivery instanceof InvoiceDelivery) {
                    // Use the id value as an index to get the id from chase step in the previous schedule
                    // at that index.
                    $step['id'] = $this->delivery->chase_schedule[$step['id']]['id'] ?? null;
                }
            }
        }

        $delivery = $delivery ?? new InvoiceDelivery();
        $delivery->invoice = $invoice;
        foreach ($data as $key => $value) {
            $delivery->$key = $value;
        }
        $delivery->saveOrFail();

        return $delivery;
    }

    private function buildBaseAssertionMessage(string $filename, string $operation): string
    {
        $fileParts = explode('/', $filename);

        return 'File: '.$fileParts[count($fileParts) - 1].' - Op: '.$operation;
    }
}
