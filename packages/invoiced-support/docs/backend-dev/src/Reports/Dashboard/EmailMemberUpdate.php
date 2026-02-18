<?php

namespace App\Reports\Dashboard;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\I18n\MoneyFormatter;
use App\Core\Mailer\Mailer;
use App\Core\Utils\ValueObjects\Interval;
use App\ActivityLog\Interfaces\EventStorageInterface;
use App\ActivityLog\Models\Event;
use App\Reports\DashboardMetrics\ActionItemsMetric;
use App\Reports\DashboardMetrics\ArBalanceMetric;
use App\Reports\DashboardMetrics\CollectionsEfficiencyMetric;
use App\Reports\DashboardMetrics\DaysSalesOutstandingMetric;
use App\Reports\DashboardMetrics\ExpectedPaymentsMetric;
use App\Reports\DashboardMetrics\OpenEstimatesMetric;
use App\Reports\DashboardMetrics\TimeToPayMetric;
use App\Reports\DashboardMetrics\TopDebtorsMetric;
use App\Reports\ValueObjects\AgingBreakdown;
use App\Reports\ValueObjects\DashboardContext;
use Carbon\CarbonImmutable;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EmailMemberUpdate
{
    private Company $company;
    private Member $member;
    private DashboardContext $context;
    private string $frequency;

    public function __construct(
        private ActivityChart $activityChart,
        private ActionItemsMetric $actionItemsMetric,
        private ExpectedPaymentsMetric $expectedPaymentsMetric,
        private TopDebtorsMetric $topDebtorsMetric,
        private TimeToPayMetric $timeToPayMetric,
        private ArBalanceMetric $arBalanceMetric,
        private DaysSalesOutstandingMetric $dsoMetric,
        private CollectionsEfficiencyMetric $collectionsEfficiencyMetric,
        private OpenEstimatesMetric $openEstimatesMetric,
        private DashboardCacheLayer $cacheLayer,
        private Mailer $mailer,
        private string $dashboardUrl,
        private EventStorageInterface $eventStorage,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Used for unit tests.
     */
    public function setMailer(Mailer $mailer): void
    {
        $this->mailer = $mailer;
    }

    public function setContext(Company $company, Member $member, string $frequency): void
    {
        $this->company = $company;
        $this->member = $member;
        $this->context = new DashboardContext($company, $member);
        $this->frequency = $frequency;

        $this->activityChart->setCompany($company);
        $this->activityChart->setMember($member);
    }

    /**
     * Computes the period for the update.
     */
    public function getPeriod(): array
    {
        $interval = new Interval(1, $this->frequency);
        $duration = $interval->duration();

        $end = time();
        $start = strtotime('-'.$duration, $end);

        return [$duration, $start, $end];
    }

    /**
     * Sends an update to the company.
     */
    public function send(): bool
    {
        if (!$this->canSend()) {
            return false;
        }

        // determine the period this update covers
        [$frequency, $start, $end] = $this->getPeriod();

        // build the email and queue it for sending
        $this->mailer->sendToUser(
            $this->member->user(),
            $this->buildMessage(),
            'digest',
            $this->buildVariables($frequency, $start, $end)
        );

        return true;
    }

    /**
     * Checks if the company can be sent an update.
     */
    private function canSend(): bool
    {
        // check if the company has an active subscription
        // and completed onboarding
        return $this->company->billingStatus()->isActive() && !$this->company->features->has('needs_onboarding');
    }

    /**
     * Builds the parameters for sending the email.
     */
    private function buildMessage(): array
    {
        $companyName = $this->company->nickname ?? $this->company->name;

        return [
            'subject' => $companyName.' Update',
        ];
    }

    /**
     * Builds the template variables for the email.
     */
    public function buildVariables(string $frequency, int $start, int $end): array
    {
        $currency = $this->company->currency;

        [$totalInvoiced, $totalPaid] = $this->getTotalInvoicedAndPaid($start, $end);
        $timeToPay = $this->cacheLayer->buildMetric($this->timeToPayMetric, $this->context, ['currency' => $currency]);
        $arBalance = $this->cacheLayer->buildMetric($this->arBalanceMetric, $this->context, ['currency' => $currency]);
        $dso = $this->cacheLayer->buildMetric($this->dsoMetric, $this->context, ['currency' => $currency]);
        $collectionsEfficiency = $this->cacheLayer->buildMetric($this->collectionsEfficiencyMetric, $this->context, ['currency' => $currency]);
        $expectedPayments = $this->cacheLayer->buildMetric($this->expectedPaymentsMetric, $this->context, ['currency' => $currency]);
        $openEstimates = $this->cacheLayer->buildMetric($this->openEstimatesMetric, $this->context, ['currency' => $currency]);

        return [
            'company' => $this->company->name,
            'companyId' => $this->company->id(),
            'start' => date($this->company->date_format, $start),
            'end' => date($this->company->date_format, $end),
            'sinceStr' => $this->getSince($frequency),
            'dashboardUrl' => $this->dashboardUrl,
            'billingUrl' => $this->dashboardUrl."/settings/billing?account={$this->company->id()}",
            'businessSettingsUrl' => $this->dashboardUrl."/settings/notifications?account={$this->company->id()}",
            'viewTopDebtorsUrl' => $this->dashboardUrl."/customers?hasBalance=1&account={$this->company->id()}",
            'invoiced' => $this->currencyFormat($totalInvoiced),
            'hasInvoiced' => $totalInvoiced > 0,
            'received' => $this->currencyFormat($totalPaid),
            'hasReceived' => $totalPaid > 0,
            'avgTimeToPay' => $timeToPay['average_time_to_pay'],
            'totalOutstanding' => $this->currencyFormat($arBalance['total_balance']),
            'numOutstanding' => $arBalance['num_open_items'],
            'topDebtors' => $this->getTopDebtors($currency),
            'aging' => $this->getAging($arBalance['aging']),
            'collectionsEfficiency' => ($collectionsEfficiency['collections_efficiency'] > 0) ? ($collectionsEfficiency['collections_efficiency'] * 100).'%' : false,
            'daysSalesOutstanding' => $dso['dso'],
            'expectedPayments' => $this->currencyFormat($expectedPayments['total']),
            'outstandingEstimates' => $this->company->features->has('estimates') ? $this->currencyFormat($openEstimates['total_estimates']) : false,
            'activity' => $this->getRecentActivity(),
            'month' => date('F'),
            'actionItems' => $this->cacheLayer->buildMetric($this->actionItemsMetric, $this->context, []),
            'hasAccountsReceivable' => $this->company->features->has('accounts_receivable'),
        ];
    }

    private function getSince(string $frequency): string
    {
        // frequency time string = 1 month|week|day
        $sinceStr = 'last '.substr($frequency, 2);
        if ('last day' == $sinceStr) {
            return 'yesterday';
        }

        return $sinceStr;
    }

    private function getTotalInvoicedAndPaid(int $start, int $end): array
    {
        $activity = $this->activityChart->generate(null, $start, $end);

        $totalInvoiced = 0;
        foreach ($activity['invoices'] as $value) {
            $totalInvoiced += $value;
        }

        $totalPaid = 0;
        foreach ($activity['payments'] as $value) {
            $totalPaid += $value;
        }

        return [$totalInvoiced, $totalPaid];
    }

    private function getTopDebtors(string $currency): array
    {
        $topDebtors = $this->cacheLayer->buildMetric($this->topDebtorsMetric, $this->context, [
            'count' => 5,
            'currency' => $currency,
        ])['top_debtors'];
        foreach ($topDebtors as &$account) {
            $account['balance'] = $this->currencyFormat($account['balance']);

            $account['age_color'] = '#54BF83';

            if ($account['pastDue']) {
                // past due
                $account['age_color'] = '#e91c2b';
            } elseif ($account['age'] < 0) {
                // future invoice
                $account['age_color'] = '#aaaaaa';
            }

            $account['link'] = $this->dashboardUrl."/customers/{$account['customer']}?account={$this->company->id()}";
        }

        return $topDebtors;
    }

    private function getAging(array $dashboardAging): array
    {
        $aging = [];
        $agingBreakdown = AgingBreakdown::fromSettings($this->company->accounts_receivable_settings);

        $max = 0;
        foreach ($dashboardAging as $row) {
            $max = max($row['amount'], $max);
        }

        foreach ($dashboardAging as $i => $row) {
            if (!$row['amount']) {
                continue;
            }

            $upper = null;
            if ($i < count($dashboardAging) - 1) {
                $upper = (int) $dashboardAging[$i + 1]['age_lower'] - 1;
            }

            $bucket = $agingBreakdown->getBucketForAge($row['age_lower']);
            $queryParams = [
                $agingBreakdown->dateColumn => json_encode([
                    'start' => $this->getIssuedAfter($upper),
                    'end' => $this->getIssuedBefore($row['age_lower']),
                ]),
                'account' => $this->company->id(),
            ];

            $aging[] = [
                'link' => $this->dashboardUrl.'/invoices?'.http_build_query($queryParams),
                'value' => $this->currencyFormat($row['amount']),
                'title' => $agingBreakdown->getBucketName($bucket, $this->translator, $this->company->getLocale()),
                'color' => $agingBreakdown->getColor($bucket),
                'width' => $max ? round($row['amount'] / $max * 100) : 0,
            ];
        }

        return $aging;
    }

    private function getRecentActivity(): array
    {
        // users with customer restrictions cannot see recent activity
        if (Member::UNRESTRICTED != $this->member->restriction_mode) {
            return [];
        }

        $events = Event::queryWithTenant($this->company)->first(5);

        // hydrate the event data because it will be used by the notification
        foreach ($events as $event) {
            $event->hydrateFromStorage($this->eventStorage);
        }

        $activity = [];
        foreach ($events as $event) {
            $activity[] = [
                'link' => $event->href,
                'message' => str_replace(['<b>', '</b>'], ['', ''], $event->getMessage()->toString()),
            ];
        }

        return $activity;
    }

    /**
     * Formats a number in the currency format.
     */
    private function currencyFormat(float $num): string
    {
        return MoneyFormatter::get()->currencyFormat(
            $num,
            $this->company->currency,
            $this->company->moneyFormat()
        );
    }

    /**
     * Gets the timestamp for issued before lower age value.
     */
    private function getIssuedBefore(int $lower): ?string
    {
        if (-1 === $lower) {
            return null;
        }

        if (0 === $lower) {
            return CarbonImmutable::now()->toDateString();
        }

        return CarbonImmutable::now()->subDays($lower)->toDateString();
    }

    /**
     * Gets the timestamp for issued after upper age value.
     */
    private function getIssuedAfter(?int $upper): ?string
    {
        if (null === $upper) {
            return null;
        }

        if (-1 === $upper) {
            return CarbonImmutable::now()->addDay()->toDateString();
        }

        return CarbonImmutable::now()->subDays($upper)->toDateString();
    }
}
