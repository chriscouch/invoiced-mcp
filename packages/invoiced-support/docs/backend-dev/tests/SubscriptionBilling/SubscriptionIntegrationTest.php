<?php

namespace App\Tests\SubscriptionBilling;

use App\SubscriptionBilling\Models\Plan;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ValueObjects\Interval;
use App\AccountsReceivable\Models\Invoice;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Models\Subscription;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Exception;
use App\Core\Orm\Model;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Yaml;
use Throwable;

class SubscriptionIntegrationTest extends AppTestCase
{
    private static array $objectDefaults = [
        'plan' => [
            'amount' => 100,
            'interval' => Interval::MONTH,
            'interval_count' => 1,
        ],
    ];
    private static array $dateProperties = [
        'created_at',
        'contract_period_start',
        'contract_period_end',
        'date',
        'due_date',
        'period_start',
        'period_end',
        'renewed_last',
        'renews_next',
        'start_date',
    ];
    private string $testName;
    private bool $debugMode;
    private string $operation;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
    }

    public function testSubscriptions(): void
    {
        foreach ($this->getTestFiles() as $filename) {
            try {
                $this->runTestCase($filename);
            } catch (Throwable $e) {
                if ($e instanceof \PHPUnit\Framework\Exception) {
                    throw $e;
                }
                throw new Exception('Uncaught error when processing "'.$filename.'" test case', $e->getCode(), $e);
            }
        }
    }

    private function getTestFiles(): iterable
    {
        $result = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/test_cases')) as $filename) {
            // filter out "." and ".."
            if (!$filename->isDir()) {
                $result[] = $filename;
            }
        }

        sort($result);

        return $result;
    }

    private function runTestCase(string $filename): void
    {
        // Parse the YAML definition
        $definition = Yaml::parseFile($filename);
        $fileParts = explode('/', $filename);
        $this->testName = explode('.', end($fileParts))[0];

        $this->debugMode = $definition['debug'] ?? false;

        if ($this->debugMode) {
            echo "\n\n---------------\n";
            echo "{$this->testName}\nDefinition:\n".json_encode($definition, JSON_PRETTY_PRINT);
        }

        // Create data
        $models = $this->createData($definition['data'] ?? []);

        // Execute each operation and perform assertions
        $operations = $definition['operations'] ?? [];
        $subscription = null;
        foreach ($operations as $i => $row) {
            $operation = $row['operation'];
            if (isset($row['name'])) {
                $this->operation = "'{$row['name']}'";
            } else {
                $this->operation = "$operation (# ".($i + 1).')';
            }
            $time = $row['time'] ?? time();
            $parameters = $row['parameters'] ?? [];
            $assertions = $row['assertions'] ?? [];
            $subscription = $this->performOperation($operation, $parameters, $time, $assertions, $models, $subscription);
        }
    }

    private function createData(array $data): array
    {
        $models = [];
        foreach ($data as $row) {
            $object = $row['object'];
            unset($row['object']);

            if (isset(self::$objectDefaults[$object])) {
                $row = array_replace(self::$objectDefaults[$object], $row);
            }

            if ('plan' == $object) {
                $row = array_replace([
                    'name' => $this->testName,
                ], $row);
            }

            $modelClass = ObjectType::fromTypeName($object)->modelClass();
            /** @var Model $model */
            $model = new $modelClass($row);
            if (!$model->save()) {
                $this->fail('Could not save '.$object.' in create data stage of "'.$this->testName.'" test: '.$model->getErrors());
            }

            $models[] = $model;

            if ($this->debugMode) {
                echo "\n\nCreated:\n".$this->outputModel($model);
            }
        }

        return $models;
    }

    private function performOperation(string $operationName, array $parameters, int $time, array $assertions, array $models, ?Subscription $subscription): Subscription
    {
        // Set the system clock (will be UNIX timestamp)
        CarbonImmutable::setTestNow('@'.$time);

        if ($this->debugMode) {
            echo "\n\nPerforming operation: $operationName\n";
            echo json_encode($parameters, JSON_PRETTY_PRINT);
        }

        $invoice = null;
        if ('create' == $operationName) {
            [$subscription, $invoice] = $this->createSubscription($parameters, $models);
        }

        if (!($subscription instanceof Subscription)) {
            $this->fail("No subscription has been created in '{$this->testName}': $operationName");
        }

        if ('bill' == $operationName) {
            $invoice = $this->billSubscription($subscription);
        } elseif ('edit' == $operationName) {
            $invoice = $this->editSubscription($subscription, $parameters);
        } elseif ('manual_contract_renewal' == $operationName) {
            $invoice = $this->manualContractRenewal($subscription, $parameters);
        } elseif ('pause' == $operationName) {
            $this->pauseSubscription($subscription);
        } elseif ('resume' == $operationName) {
            $this->resumeSubscription($subscription, $parameters);
        } elseif ('cancel' == $operationName) {
            $this->cancelSubscription($subscription);
        } elseif ('create' != $operationName) {
            $this->fail("Unrecognized operation in '{$this->testName}': $operationName");
        }

        $this->performAssertions($subscription, $invoice, $assertions);

        return $subscription;
    }

    private function performAssertions(Subscription $subscription, ?Invoice $invoice, array $assertions): void
    {
        // Perform assertions on subscription
        if (isset($assertions['subscription'])) {
            $this->performAssertionsOnModel($subscription, $assertions['subscription']);
        }

        // Perform assertions on invoice
        if (isset($assertions['invoice'])) {
            if (!($invoice instanceof Invoice)) {
                $this->fail("Invoice was not generated during {$this->operation} operation of '{$this->testName}' test'");
            }

            $this->performAssertionsOnModel($invoice, $assertions['invoice']);
        }
    }

    private function performAssertionsOnModel(Model $model, array $assertions): void
    {
        foreach ($assertions as $key => $value) {
            $testValue = $model->$key;
            if (in_array($key, self::$dateProperties)) {
                $value = $value ? CarbonImmutable::createFromTimestamp($value) : $value;
                $testValue = $testValue ? CarbonImmutable::createFromTimestamp($testValue) : $value;
            }

            $this->assertEquals($value, $testValue, "{$model::modelName()}.$key assertion failed after {$this->operation} operation of '{$this->testName}' test'");
        }
    }

    private function outputModel(Model $model): string
    {
        $array = $model->toArray();

        // Convert date properties
        foreach ($array as $key => &$value) {
            if ($value && in_array($key, self::$dateProperties)) {
                $value = CarbonImmutable::createFromTimestamp($value);
                $value = $value->format('Y-m-d H:i:s');
            }
        }

        return (string) json_encode($array, JSON_PRETTY_PRINT);
    }

    //
    // Operations
    //

    private function createSubscription(array $data, array $models): array
    {
        // create the subscription
        $data['customer'] = self::$customer;
        if (!isset($data['plan'])) {
            foreach ($models as $model) {
                if ($model instanceof Plan) {
                    $data['plan'] = $model;
                    break;
                }
            }
        }
        try {
            $subscription = self::getService('test.create_subscription')->create($data);
        } catch (OperationException $e) {
            throw new Exception("Could not save subscription during {$this->operation} operation of '{$this->testName}' test'", $e->getCode(), $e);
        }

        if ($this->debugMode) {
            echo "\n\nCreated:\n".$this->outputModel($subscription);
        }

        $invoice = null;
        if ($subscription->renewed_last) {
            $invoice = Invoice::where('subscription_id', $subscription)->one();

            if ($this->debugMode) {
                echo "\n\nInvoice was generated:\n".$this->outputModel($invoice);
            }
        }

        return [$subscription, $invoice];
    }

    private function billSubscription(Subscription $subscription): ?Invoice
    {
        try {
            $invoice = self::getService('test.bill_subscription')->bill($subscription);
        } catch (OperationException $e) {
            throw new Exception("Could not bill subscription during {$this->operation} operation of '{$this->testName}' test'", $e->getCode(), $e);
        }

        if ($this->debugMode && $invoice) {
            echo "\n\nInvoice was generated:\n".$this->outputModel($invoice);
            echo "\n\nSubscription state after invoice:\n".$this->outputModel($subscription);
        }

        return $invoice;
    }

    private function editSubscription(Subscription $subscription, array $parameters): ?Invoice
    {
        // Get last invoice if there is one
        $invoice = Invoice::where('subscription_id', $subscription)->sort('id DESC')->oneOrNull();

        self::getService('test.edit_subscription')->modify($subscription, $parameters);

        // See if a new invoice was generated
        $invoice2 = Invoice::where('subscription_id', $subscription)->sort('id DESC')->oneOrNull();
        if ($invoice2 && $invoice && $invoice2->id() == $invoice->id()) {
            $invoice2 = null;
        }

        return $invoice2;
    }

    private function pauseSubscription(Subscription $subscription): void
    {
        self::getService('test.pause_subscription')->pause($subscription);
    }

    private function resumeSubscription(Subscription $subscription, array $parameters): void
    {
        $periodEnd = null;
        if (isset($parameters['period_end'])) {
            $periodEnd = new CarbonImmutable($parameters['period_end']);
        }

        self::getService('test.resume_subscription')->resume($subscription, $periodEnd);
    }

    private function manualContractRenewal(Subscription $subscription, array $parameters): Invoice
    {
        /** @var int $cycles */
        $cycles = $parameters['cycles'] ?? -1;

        return self::getService('test.renew_contract')->renew($subscription, $cycles);
    }

    private function cancelSubscription(Subscription $subscription): void
    {
        self::getService('test.cancel_subscription')->cancel($subscription);
    }
}
