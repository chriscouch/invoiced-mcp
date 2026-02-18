<?php

namespace App\CustomerPortal\Libs;

final class CustomerPortalRedirect
{
    private const REDIRECTS = [
        'account' => [
            'route' => 'customer_portal_account',
        ],
        'credit_notes' => [
            'route' => 'customer_portal_list_credit_notes',
        ],
        'invoices' => [
            'route' => 'customer_portal_list_invoices',
        ],
        'estimates' => [
            'route' => 'customer_portal_list_estimates',
        ],
        'payments' => [
            'route' => 'customer_portal_list_payments',
        ],
        'pay' => [
            'route' => 'customer_portal_payment_form',
        ],
        'balance_forward_statement' => [
            'route' => 'customer_portal_statement',
            'requires_client_id' => true,
        ],
        'open_item_statement' => [
            'route' => 'customer_portal_statement',
            'requires_client_id' => true,
            'query' => [
                'type' => 'open_item',
            ],
        ],
        'add_payment_method' => [
            'route' => 'customer_portal_update_payment_info_form',
            'requires_client_id' => true,
        ],
        'update_billing_info' => [
            'route' => 'customer_portal_update_billing_info',
            'requires_client_id' => true,
        ],
    ];

    public static function get(string $redirectTo): ?array
    {
        return self::REDIRECTS[$redirectTo] ?? null;
    }
}
