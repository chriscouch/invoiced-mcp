<?php

namespace App\Core\Search\Driver\Elasticsearch;

class ElasticsearchIndexConfiguration
{
    const BOOLEAN = ['type' => 'boolean'];
    const DATE = ['type' => 'date'];
    const FLOAT = ['type' => 'float'];
    const KEYWORD = ['type' => 'keyword'];
    const KEYWORD_CASE_INSENSITIVE = [
        'type' => 'keyword',
        'normalizer' => 'lowercase',
    ];
    const LONG = ['type' => 'long'];
    const TEXT = ['type' => 'text'];

    const SUB_CUSTOMER_MAPPING = [
        'properties' => [
            'name' => self::TEXT,
        ],
    ];

    const CHARGE_MAPPING = [
        'properties' => [
            'gateway' => self::KEYWORD_CASE_INSENSITIVE,
            'gateway_id' => self::KEYWORD,
            'currency' => self::KEYWORD_CASE_INSENSITIVE,
            'amount' => self::FLOAT,
            'amount_refunded' => self::FLOAT,
            'status' => self::KEYWORD_CASE_INSENSITIVE,
            'disputed' => self::BOOLEAN,
            'refunded' => self::BOOLEAN,
            'payment_source' => self::PAYMENT_SOURCE_MAPPING,
        ],
    ];

    const PAYMENT_SOURCE_MAPPING = [
        'properties' => [
            // shared
            'type' => self::KEYWORD_CASE_INSENSITIVE,
            'gateway' => self::KEYWORD_CASE_INSENSITIVE,
            'gateway_id' => self::KEYWORD,
            'gateway_customer' => self::KEYWORD,
            'last4' => self::KEYWORD,
            // card
            'exp_month' => self::KEYWORD,
            'exp_year' => self::KEYWORD,
            'brand' => self::KEYWORD_CASE_INSENSITIVE,
            // bank account
            'bank_name' => self::KEYWORD_CASE_INSENSITIVE,
            'routing_number' => self::KEYWORD,
            'country' => self::KEYWORD_CASE_INSENSITIVE,
        ],
    ];

    const PLAN_MAPPING = [
        'properties' => [
            'id' => self::KEYWORD,
            'name' => self::TEXT,
            'currency' => self::KEYWORD_CASE_INSENSITIVE,
            'interval' => self::KEYWORD_CASE_INSENSITIVE,
            'interval_count' => self::LONG,
        ],
    ];

    private static array $mapping = [
        'contact' => [
            'properties' => [
                'name' => self::TEXT,
                'email' => self::KEYWORD_CASE_INSENSITIVE,
                'phone' => self::KEYWORD,
                'address1' => self::TEXT,
                'address2' => self::TEXT,
                'city' => self::KEYWORD_CASE_INSENSITIVE,
                'state' => self::KEYWORD_CASE_INSENSITIVE,
                'postal_code' => self::KEYWORD_CASE_INSENSITIVE,
                'country' => self::KEYWORD_CASE_INSENSITIVE,
                'customer' => self::SUB_CUSTOMER_MAPPING,
                '_customer' => self::KEYWORD,
                '_tenantId' => self::KEYWORD,
            ],
        ],
        'credit_note' => [
            'properties' => [
                'name' => self::TEXT,
                'number' => self::KEYWORD_CASE_INSENSITIVE,
                'purchase_order' => self::KEYWORD_CASE_INSENSITIVE,
                'currency' => self::KEYWORD_CASE_INSENSITIVE,
                'subtotal' => self::FLOAT,
                'total' => self::FLOAT,
                'balance' => self::FLOAT,
                'date' => self::DATE,
                'status' => self::KEYWORD_CASE_INSENSITIVE,
                'metadata' => self::TEXT,
                'customer' => self::SUB_CUSTOMER_MAPPING,
                '_customer' => self::KEYWORD,
                '_tenantId' => self::KEYWORD,
            ],
        ],
        'customer' => [
            'properties' => [
                'name' => self::TEXT,
                'number' => self::KEYWORD_CASE_INSENSITIVE,
                'autopay' => self::BOOLEAN,
                'payment_terms' => self::KEYWORD_CASE_INSENSITIVE,
                'payment_source' => self::PAYMENT_SOURCE_MAPPING,
                'currency' => self::KEYWORD_CASE_INSENSITIVE,
                'chase' => self::BOOLEAN,
                'attention_to' => self::TEXT,
                'address1' => self::TEXT,
                'address2' => self::TEXT,
                'city' => self::KEYWORD_CASE_INSENSITIVE,
                'state' => self::KEYWORD_CASE_INSENSITIVE,
                'postal_code' => self::KEYWORD_CASE_INSENSITIVE,
                'country' => self::KEYWORD_CASE_INSENSITIVE,
                'email' => self::KEYWORD_CASE_INSENSITIVE,
                'phone' => self::KEYWORD,
                'tax_id' => self::KEYWORD_CASE_INSENSITIVE,
                'metadata' => self::TEXT,
                '_customer' => self::KEYWORD,
                '_tenantId' => self::KEYWORD,
            ],
        ],
        'estimate' => [
            'properties' => [
                'name' => self::TEXT,
                'number' => self::KEYWORD_CASE_INSENSITIVE,
                'purchase_order' => self::KEYWORD_CASE_INSENSITIVE,
                'currency' => self::KEYWORD_CASE_INSENSITIVE,
                'subtotal' => self::FLOAT,
                'total' => self::FLOAT,
                'deposit' => self::FLOAT,
                'date' => self::DATE,
                'payment_terms' => self::KEYWORD_CASE_INSENSITIVE,
                'status' => self::KEYWORD_CASE_INSENSITIVE,
                'metadata' => self::TEXT,
                'customer' => self::SUB_CUSTOMER_MAPPING,
                '_customer' => self::KEYWORD,
                '_tenantId' => self::KEYWORD,
            ],
        ],
        'invoice' => [
            'properties' => [
                'name' => self::TEXT,
                'number' => self::KEYWORD_CASE_INSENSITIVE,
                'purchase_order' => self::KEYWORD_CASE_INSENSITIVE,
                'currency' => self::KEYWORD_CASE_INSENSITIVE,
                'subtotal' => self::FLOAT,
                'total' => self::FLOAT,
                'date' => self::DATE,
                'payment_terms' => self::KEYWORD_CASE_INSENSITIVE,
                'due_date' => self::DATE,
                'status' => self::KEYWORD_CASE_INSENSITIVE,
                'balance' => self::FLOAT,
                'autopay' => self::BOOLEAN,
                'attempt_count' => self::LONG,
                'next_payment_attempt' => self::DATE,
                'metadata' => self::TEXT,
                'customer' => self::SUB_CUSTOMER_MAPPING,
                '_customer' => self::KEYWORD,
                '_tenantId' => self::KEYWORD,
            ],
        ],
        'payment' => [
            'properties' => [
                'date' => self::DATE,
                'method' => self::KEYWORD_CASE_INSENSITIVE,
                'currency' => self::KEYWORD_CASE_INSENSITIVE,
                'amount' => self::FLOAT,
                'voided' => self::BOOLEAN,
                'source' => self::KEYWORD_CASE_INSENSITIVE,
                'reference' => self::KEYWORD_CASE_INSENSITIVE,
                'balance' => self::FLOAT,
                'charge' => self::CHARGE_MAPPING,
                'customer' => self::SUB_CUSTOMER_MAPPING,
                '_customer' => self::KEYWORD,
                '_tenantId' => self::KEYWORD,
            ],
        ],
        'subscription' => [
            'properties' => [
                'plan' => self::PLAN_MAPPING,
                'recurring_total' => self::FLOAT,
                'status' => self::KEYWORD_CASE_INSENSITIVE,
                'metadata' => self::TEXT,
                'customer' => self::SUB_CUSTOMER_MAPPING,
                '_customer' => self::KEYWORD,
                '_tenantId' => self::KEYWORD,
            ],
        ],
        'email_participant' => [
            'properties' => [
                'email_address' => self::TEXT,
                'name' => self::TEXT,
                'id' => self::KEYWORD,
                '_tenantId' => self::KEYWORD,
            ],
        ],
        'vendor' => [
            'properties' => [
                'name' => self::TEXT,
                'number' => self::KEYWORD_CASE_INSENSITIVE,
                '_vendor' => self::KEYWORD,
                '_tenantId' => self::KEYWORD,
            ],
        ],
    ];

    public static function getSettings(string $indexName): array
    {
        $indexName = self::normalizeIndexName($indexName);

        return [
            // This number might need to change in the future
            // per environment/index. The recommended shard
            // size is 50GB maximum. 2 shards permits storing a
            // maximum of 100GB per index.
            'number_of_shards' => 'email' == $indexName ? 3 : 2,
        ];
    }

    public static function getMapping(string $indexName): ?array
    {
        $indexName = self::normalizeIndexName($indexName);

        return self::$mapping[$indexName] ?? null;
    }

    /**
     * Used to strip out unique identifier from a unique
     * index name.
     * Example: `customer-6sf3221f58` produces `customer`.
     */
    public static function normalizeIndexName(string $indexName): string
    {
        $index = explode('-', $indexName);

        return $index[0];
    }
}
