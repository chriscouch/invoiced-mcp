<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MigrateFlywirePaymentMethod extends MultitenantModelMigration
{
    public function up(): void
    {
        // Create bank transfer and online payment methods
        $this->execute('INSERT IGNORE INTO PaymentMethods (tenant_id, id) SELECT tenant_id,"bank_transfer" AS id FROM PaymentMethods WHERE id="flywire"');
        $this->execute('INSERT IGNORE INTO PaymentMethods (tenant_id, id) SELECT tenant_id,"online" AS id FROM PaymentMethods WHERE id="flywire"');

        // Update the method on payments that have flywire
        $methods = ['bank_transfer', 'direct_debit', 'credit_card', 'online'];
        foreach ($methods as $method) {
            $data = $this->fetchAll('SELECT p.id FROM Payments p JOIN FlywirePayments p2 ON p2.ar_payment_id=p.id WHERE p.method="flywire" AND p2.payment_method_type="'.$method.'"');
            foreach ($data as $row) {
                $this->execute('UPDATE Payments SET method="'.$method.'" WHERE id='.$row['id']);
            }
        }
        $this->execute('UPDATE Payments SET method="credit_card" WHERE method="flywire"');

        // Apply the payment method settings to the new methods
        $data = $this->fetchAll('SELECT tenant_id, id, enabled, gateway, merchant_account_id FROM PaymentMethods WHERE id="flywire"');
        foreach ($data as $row) {
            foreach ($methods as $method) {
                $this->execute('UPDATE PaymentMethods SET enabled='.($row['enabled'] ? '1' : '0').', gateway="'.$row['gateway'].'", merchant_account_id='.$row['merchant_account_id'].' WHERE tenant_id='.$row['tenant_id'].' AND id="'.$method.'" AND enabled=0');
            }
        }

        // Remove Flywire payment methods
        $this->execute('DELETE FROM PaymentMethods WHERE id="flywire"');
    }
}
