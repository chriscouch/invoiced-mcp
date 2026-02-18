<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class DefaultPaymentSource extends MultitenantModelMigration
{
    public function change(): void
    {
        // Count the number of chargeable cards and bank accounts per customer
        // with no default payment method and who have either cards or
        // bank accounts saved (not both).
        $results = $this->query("
            SELECT Customers.id as customer_id, COUNT(Cards.customer_id) AS card_count, COUNT(BankAccounts.customer_id) AS bank_count
            FROM Customers
            LEFT JOIN Cards
                ON Customers.id = Cards.customer_id
            LEFT JOIN BankAccounts
                ON Customers.id = BankAccounts.customer_id
            WHERE default_source_id IS NULL
                AND (Cards.chargeable = TRUE OR BankAccounts.chargeable = TRUE)
                AND (Cards.created_at >= '2021-04-14 00:00:00' OR BankAccounts.created_at >= '2021-04-14 00:00:00')
            GROUP BY Customers.id
            HAVING (card_count > 0 AND bank_count = 0)
                OR (card_count = 0 AND bank_count > 0)");

        // Separate between the customers by payment method.
        $cardCustomers = [];
        $bankCustomers = [];
        foreach ($results as $result) {
            if (0 == $result['card_count']) {
                $bankCustomers[] = $result['customer_id'];
            } else {
                $cardCustomers[] = $result['customer_id'];
            }
        }

        // Set the default payment method on those customers
        // whose only saved payment methods are credit cards.
        // NOTE: Uses the last credit card.
        if (count($cardCustomers) > 0) {
            $this->execute("
                UPDATE Customers
                SET
                    default_source_type = 'card',
                    default_source_id = (
                        SELECT id FROM Cards
                        WHERE customer_id = Customers.id
                            AND chargeable = TRUE
                        ORDER BY id DESC LIMIT 1
                    )
                WHERE Customers.id IN (".implode(',', $cardCustomers).')');
        }

        // Set the default payment method on those customers
        // whose only saved payment methods are bank accounts.
        // NOTE: Uses the last bank account saved.
        if (count($bankCustomers) > 0) {
            $this->execute("
                UPDATE Customers
                SET
                    default_source_type = 'bank_account',
                    default_source_id = (
                        SELECT id FROM BankAccounts
                        WHERE customer_id = Customers.id
                            AND chargeable = TRUE
                        ORDER BY id DESC LIMIT 1
                    )
                WHERE Customers.id IN (".implode(',', $bankCustomers).')');
        }
    }
}
