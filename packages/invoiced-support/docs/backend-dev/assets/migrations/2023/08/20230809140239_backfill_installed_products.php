<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BackfillInstalledProducts extends MultitenantModelMigration
{
    private const PRODUCTS = [
        ['id' => 'accounts_payable', 'name' => 'Accounts Payable'],
        ['id' => 'advanced_accounts_payable', 'name' => 'Advanced Accounts Payable'],
        ['id' => 'accounts_receivable', 'name' => 'Accounts Receivable'],
        ['id' => 'cash_application', 'name' => 'Cash Application'],
        ['id' => 'collections', 'name' => 'Advanced Accounts Receivable'],
        ['id' => 'customer_portal', 'name' => 'Customer Portal'],
        ['id' => 'intacct', 'name' => 'Intacct Integration'],
        ['id' => 'netsuite', 'name' => 'NetSuite Integration'],
        ['id' => 'reporting', 'name' => 'Advanced Reporting'],
        ['id' => 'salesforce', 'name' => 'Salesforce Integration'],
        ['id' => 'subscription_billing', 'name' => 'Subscription Billing'],
    ];

    public function up(): void
    {
        foreach (self::PRODUCTS as $product) {
            $this->execute('INSERT IGNORE INTO Products (name) VALUES ("'.$product['name'].'")');
            $this->execute('INSERT IGNORE INTO InstalledProducts (tenant_id, product_id, installed_on) SELECT tenant_id, (SELECT id FROM Products WHERE name="'.$product['name'].'") AS product_id, c.created_at AS installed_on FROM Features f JOIN Companies c on f.tenant_id = c.id WHERE f.feature="module_'.$product['id'].'" and enabled=1');
        }
    }
}
