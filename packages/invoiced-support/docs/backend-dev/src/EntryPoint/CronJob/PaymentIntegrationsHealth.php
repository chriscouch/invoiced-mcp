<?php

namespace App\EntryPoint\CronJob;

use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

class PaymentIntegrationsHealth implements CronJobInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(private readonly Connection $database)
    {
    }

    public static function getLockTtl(): int
    {
        return 59;
    }

    public function execute(Run $run): void
    {
        $scope = CarbonImmutable::now()->subDays(30)->toIso8601String();

        $cnt = $this->database->fetchOne('select count(*) from
            (
            select reference, REPLACE(REGEXP_SUBSTR(result, "pspReference\":\"([A-Z0-9]+)"), "pspReference\":\"", "") as id
            from AdyenPaymentResults
            where REGEXP_SUBSTR(result, "Refused") = ""
            and REGEXP_SUBSTR(result, "value\".0") = ""
            and REGEXP_SUBSTR(result, "CANCELLED") = ""
            ) a

            LEFT JOIN (Select gateway_id from Charges where gateway = \'flywire_payments\') c ON a. id = c.gateway_id
            LEFT JOIN PaymentFlows pf ON a.reference = pf.identifier

            where c.gateway_id is null
            AND pf.id is not null');

        $this->statsd->updateStats('payment_integrations_health.adyen.missing', $cnt);

        $cnt = $this->database->fetchOne('select count(*) from MerchantAccountTransactions t 
                    left  join Charges ch on t.id = ch.merchant_account_transaction_id
            where (ch.amount <> t.amount OR (ch.amount  is null) ) and t.type = 1 and (ch.currency = t.currency or ch.currency is null)
            and t.created_at > :created_at', [
            'created_at' => $scope,
        ]);

        $this->statsd->updateStats('payment_integrations_health.adyen.missmatch', $cnt);

        $cnt = $this->database->fetchOne('select count(*) from InitiatedCharges where created_at > :created_at', [
            'created_at' => $scope,
        ]);

        $this->statsd->updateStats('payment_integrations_health.initiated_charges', $cnt);

        $cnt = $this->database->fetchOne("select count(*) from (select count(*) cnt from Charges 
where status = 'succeeded' and payment_flow_id is not null
group by amount, customer_id, payment_flow_id
having cnt > 1) a");
        $this->statsd->updateStats('payment_integrations_health.duplicated_payments', $cnt);

        $cnt = $this->database->fetchOne("select count(*) from AdyenReports where processed = 0 AND report_type IN ('balanceplatform_payout_report', 'balanceplatform_accounting_report') ");
        $this->statsd->updateStats('payment_integrations_health.adyen_reports_failed', $cnt);

        //INV-250
        $cnt = $this->database->fetchOne("select count(*) cnt from Charges group by tenant_id, gateway, gateway_id having cnt > 1");
        $this->statsd->updateStats('payment_integrations_health.mor_duplicates', $cnt);

        //INV-449
        $cnt = $this->database->fetchAllNumeric('select count(*) cnt, REGEXP_REPLACE(filename, "balanceplatform_[a-z_]+(.*)\.csv", "\\1") as date from AdyenReports where id >= 523 AND (filename LIKE "balanceplatform_accounting_report_%" OR filename LIKE "balanceplatform_payout_report_%") GROUP BY date HAVING cnt < 2');
        $this->statsd->updateStats('payment_integrations_health.missing-reports', count($cnt));

        //INV-443
        $cnt = $this->database->fetchOne('select COUNT(*) cnt from Charges WHERE payment_flow_id IN (select id FROM PaymentFlows where status IN (1,3,4) AND completed_at) AND status != "pending"');
        $this->statsd->updateStats('payment_integrations_health.broken_payment_flows', $cnt);
    }

    public static function getName(): string
    {
        return 'payment_integrations_health';
    }
}
