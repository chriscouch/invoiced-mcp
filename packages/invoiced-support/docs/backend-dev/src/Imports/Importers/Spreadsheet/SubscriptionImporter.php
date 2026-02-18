<?php

namespace App\Imports\Importers\Spreadsheet;

use App\Core\Database\TransactionManager;
use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\Exceptions\ValidationException;
use App\Imports\Libs\ImportHelper;
use App\Imports\Models\Import;
use App\Imports\Traits\ImportHasCustomerTrait;
use App\Imports\ValueObjects\ImportRecordResult;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Operations\CreateSubscription;

class SubscriptionImporter extends BaseSpreadsheetImporter
{
    use ImportHasCustomerTrait;

    private const LINE_ITEM_PROPERTIES = [
        'plan' => 'plan',
        'quantity' => 'quantity',
        'description' => 'description',
        'amount' => 'amount',
    ];

    public function __construct(private CreateSubscription $createSubscription, TransactionManager $transactionManager)
    {
        parent::__construct($transactionManager);
    }

    public function build(array $mapping, array $lines, array $options, Import $import): array
    {
        $options['operation'] ??= self::CREATE;

        $data = [];

        // This is a map that matches subscriptions by customer to
        // its index in the import. This allows subscriptions
        // to have addons
        $subscriptionMap = [];

        foreach ($lines as $i => $line) {
            if (!is_array($line)) {
                continue;
            }

            try {
                $record = $this->buildRecord($mapping, $line, $options, $import);

                // parse discounts
                if (ImportHelper::cellHasValue($record, 'discounts')) {
                    $record['discounts'] = explode(',', $record['discounts']);
                } elseif (isset($record['discounts'])) {
                    unset($record['discounts']);
                }

                if (isset($record['snap_to_nth_day'])) {
                    $record['snap_to_nth_day'] = (int) $record['snap_to_nth_day'];
                }

                if (isset($record['bill_in_advance_days'])) {
                    $record['bill_in_advance_days'] = (int) $record['bill_in_advance_days'];
                }

                // quantity defaults to 1 when not provided
                if (!ImportHelper::cellHasValue($record, 'quantity')) {
                    $record['quantity'] = 1;
                }

                // build line items
                $lineItem = [];
                foreach (self::LINE_ITEM_PROPERTIES as $k => $property) {
                    if (ImportHelper::cellHasValue($record, $k)) {
                        $lineItem[$property] = $record[$k];
                        unset($record[$k]);
                    }
                }

                // merge multiple line items by an identifier that
                // is located in this order:
                // 1. customer number
                // 2. customer name
                $importIdentifier = array_value($record, 'customer.number');
                if (!$importIdentifier) {
                    $importIdentifier = array_value($record, 'customer.name');
                }
                if (!$importIdentifier) {
                    throw new ValidationException('Missing subscription line identifier. The line must include at least one of customer # or customer name.');
                }

                if (isset($subscriptionMap[$importIdentifier])) {
                    $parent = $subscriptionMap[$importIdentifier];

                    if (!isset($data[$parent]['addons'])) {
                        $data[$parent]['addons'] = [];
                    }

                    // merge line items with parent addons
                    $data[$parent]['addons'][] = $lineItem;
                } else {
                    $record = array_merge($record, $lineItem);
                    $data[] = $record;

                    // create new parent to handle multiple lines
                    $subscriptionMap[$importIdentifier] = count($data) - 1;
                }
            } catch (ValidationException $e) {
                // decorate exception with
                // line number/record and rethrow
                $e->setLineNumber($i + 2)
                    ->setRecord($line);

                throw $e;
            }
        }

        return $data;
    }

    protected function findExistingRecord(array $record): ?Model
    {
        // Finding existing subscriptions is currently not supported.
        return null;
    }

    protected function createRecord(array $record): ImportRecordResult
    {
        if (isset($record['customer'])) {
            // The CreateSubscription operation accepts a model for this parameter
            $record['customer'] = $this->getCustomerObject($record['customer']);
        }

        try {
            $subscription = $this->createSubscription->create($record);
        } catch (OperationException $e) {
            throw new RecordException('Could not create subscription: '.$e->getMessage());
        }

        return new ImportRecordResult($subscription, ImportRecordResult::CREATE);
    }
}
