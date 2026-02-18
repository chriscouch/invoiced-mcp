<?php

namespace App\Reports\PresetReports;

use App\Core\Ledger\Ledger;
use App\Core\Ledger\Repository\LedgerRepository;
use App\Integrations\WePay\Models\WePayData;
use App\PaymentProcessing\Models\MerchantAccount;
use App\Reports\ValueObjects\KeyValueGroup;
use App\Reports\ValueObjects\Section;

abstract class AbstractInvoicedPaymentsReport extends AbstractReport
{
    protected function getWePayData(): ?WePayData
    {
        return WePayData::find($this->company->id());
    }

    protected function getMerchantAccount(): ?MerchantAccount
    {
        return MerchantAccount::where('gateway', 'invoiced')
            ->sort('deleted asc')
            ->one();
    }

    protected function getLedger(MerchantAccount $merchantAccount): ?Ledger
    {
        $ledgerRepository = new LedgerRepository($this->database);

        return $ledgerRepository->find('Invoiced Payments - '.$merchantAccount->gateway_id);
    }

    protected function addOverviewGroup(WePayData $wePayData): void
    {
        $overview = new KeyValueGroup();

        if ($readCursor = $wePayData->read_cursor) {
            $overview->addLine('Latest Data Available', $readCursor->format($this->dateTimeFormat));
        }

        $section = new Section('');
        $section->addGroup($overview);
        $this->report->addSection($section);
    }

    protected function noData(): void
    {
        $overview = new KeyValueGroup();
        $overview->addLine('No Data Available', '');
        $section = new Section('');
        $section->addGroup($overview);
        $this->report->addSection($section);
    }
}
