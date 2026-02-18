<?php

namespace App\Integrations\ChartMogul\Syncs;

use App\AccountsReceivable\Models\Customer;
use App\Core\Utils\ModelUtility;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\ChartMogul\Models\ChartMogulAccount;
use ChartMogul\Customer as ChartMogulCustomer;
use ChartMogul\Exceptions\ChartMogulException;
use App\Core\Utils\InfuseUtility as Utility;

class CustomerSync extends AbstractSync
{
    public static function getDefaultPriority(): int
    {
        return 20;
    }

    public function sync(ChartMogulAccount $account): void
    {
        $this->logger->info('Syncing customers to ChartMogul');

        // Sync each customer from Invoiced that has been updated since last sync.
        // Only include customers that have an invoice or credit note for performance
        // reasons. If a customer has no transactions then it will be an empty profile
        // in ChartMogul.
        $query = Customer::where('updated_at', Utility::unixToDb($account->sync_cursor), '>')
            ->where('(EXISTS (SELECT 1 FROM Invoices WHERE customer=Customers.id) OR EXISTS (SELECT 1 FROM CreditNotes WHERE customer=Customers.id))');
        $customers = ModelUtility::getAllModelsGenerator($query);
        foreach ($customers as $customer) {
            try {
                $this->syncCustomer($customer, $account);
            } catch (ChartMogulException $e) {
                throw new SyncException($customer->name.': '.$e->getMessage(), $e->getCode(), $e);
            }
        }

        // Clear cache once complete
        $this->clearCache();
    }

    /**
     * Converts an Invoiced customer to a ChartMogul customer.
     */
    public function buildCustomerParams(Customer $customer, ChartMogulAccount $account): array
    {
        $params = [
            'data_source_uuid' => $account->data_source,
            'external_id' => (string) $customer->id,
            'name' => $customer->name,
            'email' => $customer->email ?? '',
            'city' => $customer->city,
            'state' => $customer->state,
            'zip' => $customer->postal_code,
            'country' => $customer->country,
            'attributes' => [
                'custom' => [],
            ],
        ];

        foreach ((array) $customer->metadata as $key => $value) {
            // determine the data type of the metadata
            $dataType = 'String';
            if (is_bool($value)) {
                $dataType = 'Boolean';
            } elseif (is_float($value)) {
                $dataType = 'Decimal';
            } elseif (is_int($value)) {
                $dataType = 'Integer';
            }

            $params['attributes']['custom'][] = [
                'type' => $dataType,
                'key' => $key,
                'value' => $value,
                'source' => 'Invoiced',
            ];
        }

        return $params;
    }

    /**
     * Syncs an Invoiced customer to ChartMogul.
     */
    private function syncCustomer(Customer $customer, ChartMogulAccount $account): void
    {
        $params = $this->buildCustomerParams($customer, $account);

        // Update or create the customer on ChartMogul
        $existing = $this->lookupCustomer($customer->id, false);
        if ($existing) {
            $this->updateCustomer($existing, $params);
        } else {
            $this->createCustomer($params);
        }
    }

    /**
     * Creates a new customer on ChartMogul.
     */
    private function createCustomer(array $params): void
    {
        ChartMogulCustomer::create($params);
    }

    /**
     * Updates an existing customer on ChartMogul.
     */
    private function updateCustomer(ChartMogulCustomer $existing, array $params): void
    {
        $attributes = $params['attributes'];
        unset($params['attributes']);
        unset($params['data_source_uuid']);
        unset($params['external_id']);

        // update customer
        $customerId = $existing->uuid;
        ChartMogulCustomer::update([
            'customer_uuid' => $customerId,
        ], $params);

        // set custom attributes from metadata
        if (count($attributes['custom']) > 0) {
            $existing->addCustomAttributes(...$attributes['custom']);
        }
    }
}
