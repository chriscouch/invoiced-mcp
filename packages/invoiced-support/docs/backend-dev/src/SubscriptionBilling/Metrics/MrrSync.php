<?php

namespace App\SubscriptionBilling\Metrics;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\SubscriptionBilling\Models\MrrVersion;
use App\SubscriptionBilling\ValueObjects\MrrCalculationState;
use Carbon\CarbonImmutable;
use Symfony\Component\Console\Output\OutputInterface;

class MrrSync
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentMrrSync $documentMrrSync,
        private MrrMovementSync $mrrMovementSync,
    ) {
    }

    public function sync(Company $company, OutputInterface $output, bool $refresh): void
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        $company->useTimezone();
        $startTime = CarbonImmutable::now();

        // Create or retrieve the version
        if ($refresh) {
            $version = null;
        } else {
            $version = $company->subscription_billing_settings->mrr_version;
        }

        if (!$version) {
            $version = new MrrVersion();
            $version->currency = $company->currency;
            $version->saveOrFail();
            $refresh = true;
        }

        // Sync transactions into MRR items
        $state = new MrrCalculationState($version, $output);
        $this->documentMrrSync->sync($state, Invoice::class);
        $this->documentMrrSync->sync($state, CreditNote::class);

        // Calculate MRR movements from the generated MRR items
        $this->mrrMovementSync->sync($state);

        // Set last updated
        $version->last_updated = $startTime;
        $version->saveOrFail();

        $output->writeln('Saved metrics for version # '.$version->id);

        if ($refresh) {
            // Update settings to point to new MRR version
            $company->subscription_billing_settings->mrr_version = $version;
            $company->subscription_billing_settings->saveOrFail();

            // Delete previous MRR versions
            $previousVersions = MrrVersion::where('id', $version->id, '<>')->first(100);
            foreach ($previousVersions as $previousVersion) {
                $previousVersion->deleteOrFail();
                $output->writeln('Deleted metrics for version # '.$previousVersion->id);
            }
        }

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();
    }
}
