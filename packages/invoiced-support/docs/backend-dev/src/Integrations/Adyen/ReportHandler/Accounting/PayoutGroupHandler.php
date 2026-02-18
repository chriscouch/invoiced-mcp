<?php

namespace App\Integrations\Adyen\ReportHandler\Accounting;

use App\Integrations\Adyen\Operations\SaveAdyenPayout;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Reconciliation\MerchantAccountLedger;

class PayoutGroupHandler extends AbstractGroupHandler
{
    public function __construct(
        MerchantAccountLedger $merchantAccountLedger,
        bool $adyenLiveMode,
        private SaveAdyenPayout $createPayout,
    ) {
        parent::__construct($merchantAccountLedger, $adyenLiveMode);
    }

    public function handleRows(MerchantAccount $merchantAccount, string $identifier, array $rows): void
    {
        $this->createPayout->save($identifier, $merchantAccount);
    }
}
