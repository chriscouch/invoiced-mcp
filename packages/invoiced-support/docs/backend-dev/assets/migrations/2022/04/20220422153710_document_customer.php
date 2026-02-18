<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DocumentCustomer extends MultitenantModelMigration
{
    public function change(): void
    {
        // update invoice splits
        $rows = $this->fetchAll('select t.id from Transactions t inner join Invoices i on i.id = t.invoice where t.customer != i.customer and t.payment_id IS NOT NULL');
        $this->performUpdateWithIds('update Transactions t inner join Invoices i on i.id = t.invoice set t.customer = i.customer where t.id in (?)', array_map(fn ($row) => $row['id'], $rows));
    }

    /**
     * Executes a statement which includes a "WHERE IN (?)" clause in chunks of 1000,
     * replacing the '?' with each chunk of ids.
     */
    private function performUpdateWithIds(string $statement, array $ids): void
    {
        if (0 == count($ids)) {
            return;
        }

        $len = 1000;
        $offset = 0;
        $subIds = array_splice($ids, $offset, $offset + $len);
        while (count($subIds) > 0) {
            $this->execute(str_replace('?', implode(',', $subIds), $statement));
            $offset += $len;
            $subIds = array_splice($ids, $offset, $offset + $len);
        }
    }
}
