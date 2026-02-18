<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PayableDocumentUniqueNumbers extends MultitenantModelMigration
{
    public function change(): void
    {
        do {
            $affected = $this->execute("UPDATE Bills a JOIN 
                (SELECT count(*) cnt, MAX(id) id
                    FROM Bills
                    GROUP BY vendor_id, number
                    HAVING cnt > 1
                ) b
                ON a.id = b.id
                SET a.number = CONCAT(a.number, '-', b.cnt) 
                ");
        } while ($affected > 0);

        do {
            $affected = $this->execute("UPDATE VendorCredits a JOIN 
                (SELECT count(*) cnt, MAX(id) id
                    FROM VendorCredits
                    GROUP BY vendor_id, number
                    HAVING cnt > 1
                ) b
                ON a.id = b.id
                SET a.number = CONCAT(a.number, '-', b.cnt) 
                ");
        } while ($affected > 0);

        $this->table('Bills')
            ->addIndex(['vendor_id', 'number'], ['unique' => true])
            ->update();
        $this->table('VendorCredits')
            ->addIndex(['vendor_id', 'number'], ['unique' => true])
            ->update();
    }
}
