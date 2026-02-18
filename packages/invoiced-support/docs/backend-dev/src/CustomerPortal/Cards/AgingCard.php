<?php

namespace App\CustomerPortal\Cards;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;
use App\CustomerPortal\Interfaces\CardInterface;
use App\CustomerPortal\Libs\CustomerPortal;
use App\Reports\Libs\AgingReport;
use App\Reports\ValueObjects\AgingBreakdown;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

class AgingCard implements CardInterface
{
    public function __construct(
        private Connection $database,
        private TranslatorInterface $translator,
    ) {
    }

    public function getData(CustomerPortal $customerPortal): array
    {
        $company = $customerPortal->company();
        /** @var Customer $customer */
        $customer = $customerPortal->getSignedInCustomer();

        $aging = $this->getAging($company, $customer, $customerPortal);
        $totalCount = 0;
        $totalAmount = new Money($aging[0]['_amount']->currency, 0);
        foreach ($aging as $entry) {
            $totalCount += $entry['count'];
            $totalAmount = $totalAmount->add($entry['_amount']);
        }

        return [
            'aging' => $aging,
            'hasBalance' => !$totalAmount->isZero(),
            'totalCount' => $totalCount,
            'totalAmount' => $totalAmount->toDecimal(),
            'currency' => $totalAmount->currency,
        ];
    }

    private function getAging(Company $company, Customer $customer, CustomerPortal $customerPortal): array
    {
        $agingBreakdown = AgingBreakdown::fromSettings($company->accounts_receivable_settings);
        $aging = new AgingReport($agingBreakdown, $company, $this->database);
        $currency = $customer->calculatePrimaryCurrency();
        $agingReport = $aging->buildForCustomer((int) $customer->id(), $currency)[$customer->id()];

        $total = new Money($currency, 0);
        foreach ($agingReport as $row) {
            if ($row['amount']->isPositive()) {
                $total = $total->add($row['amount']);
            }
        }

        $customerAging = [];
        $agingBuckets = $agingBreakdown->getBuckets();
        foreach ($agingBuckets as $i => $bucket) {
            // severity is a value 1 (lowest) - 6 (highest)
            // this maps an arbitrary number of aging buckets (not always 6)
            // onto this severity range
            $severity = ceil((($i + 1) * 6) / count($agingBuckets));

            $amount = $agingReport[$i]['amount'];
            $customerAging[] = [
                'name' => $agingBreakdown->getBucketName($bucket, $this->translator, $customerPortal->getLocale()),
                'amount' => $amount->toDecimal(),
                '_amount' => $amount,
                'count' => $agingReport[$i]['count'],
                'color' => $agingBreakdown->getColor($bucket),
                'severity' => $severity,
                'width' => !$total->isZero() ? round(($amount->toDecimal() / $total->toDecimal()) * 100 * 1000) / 1000.0 : 0,
            ];
        }

        return $customerAging;
    }
}
