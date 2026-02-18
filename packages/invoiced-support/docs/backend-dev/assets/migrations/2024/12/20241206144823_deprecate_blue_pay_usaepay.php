<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DeprecateBluePayUsaepay extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->execute('UPDATE MerchantAccounts SET deleted=1 WHERE deleted=0 AND gateway IN ("bluepay", "usaepay")');
        $this->execute('UPDATE BankAccounts SET chargeable=0 WHERE chargeable=1 AND gateway IN ("bluepay", "usaepay")');
        $this->execute('UPDATE Cards SET chargeable=0 WHERE chargeable=1 AND gateway IN ("bluepay", "usaepay")');
        $this->execute('UPDATE PaymentMethods SET gateway="flywire", merchant_account_id=(SELECT id FROM MerchantAccounts WHERE tenant_id=PaymentMethods.tenant_id AND deleted=0 AND gateway="flywire" ORDER BY ID desc LIMIT 1) WHERE merchant_account_id IS NULL AND gateway IS NULL AND id="flywire";');
        $this->execute('UPDATE PaymentMethods SET merchant_account_id=NULL, gateway=NULL, enabled=0 WHERE enabled=1 AND gateway IN ("bluepay", "usaepay")');
    }
}
