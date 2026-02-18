<?php

namespace App\EntryPoint\CronJob;

use App\Companies\Models\Company;
use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Interfaces\BillingPeriodInterface;
use App\Core\Billing\Interfaces\UsageInterface;
use App\Core\Billing\Models\AbstractUsageRecord;
use App\Core\Billing\Models\UsagePricingPlan;
use App\Core\Billing\Usage\UsageFactory;
use App\Core\Billing\ValueObjects\MonthBillingPeriod;
use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;
use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Mailer\Mailer;
use App\Core\Multitenant\TenantContext;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class UpdateCurrentMonthUsage implements CronJobInterface, LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    public function __construct(
        private TenantContext $tenant,
        private string $dashboardUrl,
        private UsageFactory $usageFactory,
        private Mailer $mailer
    ) {
    }

    public static function getName(): string
    {
        return 'update_current_month_usage';
    }

    public static function getLockTtl(): int
    {
        return 900;
    }

    public function execute(Run $run): void
    {
        $billingPeriod = MonthBillingPeriod::fromTimestamp(strtotime('-1 day'));
        $n = $this->updateAll($billingPeriod);

        $run->writeOutput("Updated usage for $n companies in {$billingPeriod->getName()} billing period");
    }

    /**
     * Updates customer volumes for all active companies.
     *
     * @return int # updated
     */
    public function updateAll(BillingPeriodInterface $billingPeriod): int
    {
        $companies = Company::where('canceled', false)
            ->all();

        $n = 0;

        $this->statsd->gauge('cron.task_queue_size', count($companies), 1, ['cron_job' => static::getName()]);

        $usages = [];
        foreach (UsageType::cases() as $usageType) {
            $usage = $this->usageFactory->get($usageType);
            if ($usage->supports($billingPeriod)) {
                $usages[] = [$usageType, $usage];
            }
        }

        foreach ($companies as $company) {
            // IMPORTANT: set the current tenant to enable multitenant operations
            $this->tenant->set($company);

            // update the usage for each metric that we track
            foreach ($usages as $row) {
                /**
                 * @var UsageType      $usageType
                 * @var UsageInterface $usage
                 */
                [$usageType, $usage] = $row;
                $usageRecord = $usage->calculateUsage($company, $billingPeriod);

                // send the user a notification that they have exceeded their included usage
                // if there is a usage pricing plan in place and the usage type allows notification
                if ($usage->canSendOverageNotification()) {
                    $pricingPlan = UsagePricingPlan::where('tenant_id', $company)
                        ->where('usage_type', $usageType->value)
                        ->oneOrNull();

                    if ($pricingPlan && $usageRecord->count >= $pricingPlan->threshold) {
                        $this->sendOverageNotification($usageRecord, $pricingPlan);
                    }
                }
            }

            ++$n;

            // IMPORTANT: clear the current tenant after we are done
            $this->tenant->clear();
        }

        $this->statsd->updateStats('cron.processed_task', $n, 1.0, ['cron_job' => static::getName()]);

        return $n;
    }

    /**
     * Sends a notification about an overage.
     */
    public function sendOverageNotification(AbstractUsageRecord $usageRecord, UsagePricingPlan $pricingPlan): void
    {
        // can only send overage notifications for the current month
        if ($usageRecord->month != MonthBillingPeriod::now()->getName()) {
            return;
        }

        // determine if a notification has already been sent
        $company = $usageRecord->tenant();
        if ($company->last_overage_notification >= $usageRecord->month) {
            return;
        }

        // never send overages if the month is marked as do not bill
        if ($usageRecord->do_not_bill) {
            return;
        }

        $quota = $pricingPlan->threshold;
        $usage = $usageRecord->count;
        $percent = $quota > 0 ? min($usage / $quota * 100, 100) : 100;

        $metricName = $usageRecord->getMetricName();
        $metricNamePlural = $usageRecord->getMetricNamePlural();
        $usage = number_format($usage, 0).' ';
        $usage .= 1 == $usage ? $metricName : $metricNamePlural;

        $usageColor = '#54BF83';
        if ($percent >= 95) {
            $usageColor = '#9E0510';
        } elseif ($percent >= 75) {
            $usageColor = '#E7ED1A';
        }

        $month = date('F');

        $message = [
            'subject' => $month.' quota reached for '.$company->name,
            'to' => [
                [
                    'email' => $company->email,
                    'name' => $company->name,
                ],
            ],
        ];

        $overagePrice = MoneyFormatter::get()->format(Money::fromDecimal('usd', $pricingPlan->unit_price), $company->moneyFormat());

        $templateVars = [
            'company' => $company->name,
            'usage' => $usage,
            'quota' => $quota,
            'percent' => $percent,
            'usageColor' => $usageColor,
            'month' => $month,
            'upgradeUrl' => "{$this->dashboardUrl}/upgrade?account={$usageRecord->tenant_id}",
            'billingUrl' => "{$this->dashboardUrl}/settings/billing?account={$usageRecord->tenant_id}",
            'metricName' => strtolower($metricName),
            'overagePrice' => $overagePrice,
        ];

        $this->mailer->sendToAdministrators($company, $message, 'overquota', $templateVars);

        $company->last_overage_notification = $usageRecord->month;
        $company->save();
    }
}
