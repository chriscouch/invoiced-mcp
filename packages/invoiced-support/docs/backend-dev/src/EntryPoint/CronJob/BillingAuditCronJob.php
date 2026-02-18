<?php

namespace App\EntryPoint\CronJob;

use App\Core\Billing\Audit\BillingAudit;
use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Mailer\Mailer;

class BillingAuditCronJob implements CronJobInterface
{
    public function __construct(
        private BillingAudit $billingAudit,
        private string $environment,
        private Mailer $mailer,
    ) {
    }

    public static function getName(): string
    {
        return 'billing_audit';
    }

    public static function getLockTtl(): int
    {
        return 3600; // 1 hour
    }

    public function execute(Run $run): void
    {
        // Only run this audit in production
        if ('production' != $this->environment) {
            return;
        }

        $discrepancies = $this->billingAudit->auditAll($run, false);

        if (count($discrepancies) > 0) {
            $csv = $this->billingAudit->generateCsv();
            $name = 'Invoiced Billing Discrepancies - '.date('Y-m-d');

            // Send an email
            $this->mailer->send([
                'from_email' => 'no-reply@invoiced.com',
                'to' => [['email' => 'invoiced-billing-ops-aaaaobliycbamvlzcmxqhrkmtq@flywire.slack.com', 'name' => 'Billing Ops']],
                'subject' => $name,
                'text' => 'Billing discrepancies discovered: '.$this->billingAudit->getNumDiscrepancies(),
                'attachments' => [
                    [
                        'name' => $name.'.csv',
                        'type' => 'text/csv',
                        'content' => base64_encode((string) file_get_contents($csv)),
                    ],
                ],
            ]);
        }
    }
}
