<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveBitcoinPaymentMethod extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('DELETE FROM PaymentMethods WHERE id="bitcoin"');
    }
}
