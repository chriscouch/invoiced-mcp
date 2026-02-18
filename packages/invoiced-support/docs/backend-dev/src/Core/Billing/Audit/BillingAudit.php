<?php

namespace App\Core\Billing\Audit;

use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Interfaces\BillingSystemInterface;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\ValueObjects\BillingSubscriptionItem;
use App\Core\Billing\ValueObjects\BillingSystemSubscription;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Csv\CsvWriter;
use App\Core\Files\Interfaces\FileCreatorInterface;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\InfuseUtility as Utility;
use Carbon\CarbonImmutable;
use mikehaertl\tmp\File;
use RuntimeException;

/**
 * Compares Invoiced data with the billing system to detect discrepancies.
 * This verifies that the total and billing interval in the billing system
 * matches the database.
 */
class BillingAudit
{
    private const DISCREPANCY_COLUMNS = [
        'Billing Profile ID',
        'Billing Profile Name',
        'Billing System',
        'Stripe Customer',
        'Invoiced Customer',
        'Reason',
        'Amount',
        'Line Item Name',
        'Line Item Description',
        'Line Item Quantity',
        'Line Item Unit Cost',
        'Line Item Total',
        'Custom Pricing',
    ];

    private array $discrepancies = [];
    private array $hasDiscrepancy = [];
    private int $numDiscrepancies = 0;

    public function __construct(
        private readonly FileCreatorInterface $s3FileCreator,
        private readonly string        $bucket,
        private BillingItemFactory     $billingItemFactory,
        private BillingSystemFactory   $billingSystemFactory,
    ) {
    }

    /**
     * Audits all billing profiles to check for discrepancies with the billing system.
     *
     * @param bool $rebuild rebuild the subscription if there are no discrepancies
     */
    public function auditAll(Run $run, bool $rebuild): array
    {
        $this->discrepancies = [];
        $this->hasDiscrepancy = [];
        $this->numDiscrepancies = 0;

        $billingProfiles = BillingProfile::where('billing_system', ['stripe', 'invoiced', 'reseller'], '=')
            ->where('EXISTS (SELECT 1 FROM Companies WHERE billing_profile_id=BillingProfiles.id AND canceled=0)')
            ->all();

        foreach ($billingProfiles as $billingProfile) {
            $this->audit($billingProfile, $rebuild, $run);
        }

        $run->writeOutput('Found '.$this->numDiscrepancies.' discrepancies. Checked '.count($billingProfiles).' billing profiles.');

        return $this->discrepancies;
    }

    public function getNumDiscrepancies(): int
    {
        return $this->numDiscrepancies;
    }

    public function generateCsv(): File
    {
        $temp = new File('', 'csv');
        $tempFileName = $temp->getFileName();
        $fp = fopen($tempFileName, 'w');
        if (!$fp) {
            throw new RuntimeException('Could not open temp file');
        }

        CsvWriter::write($fp, self::DISCREPANCY_COLUMNS);
        foreach ($this->discrepancies as $row) {
            CsvWriter::write($fp, $row);
        }

        fclose($fp);

        return $temp;
    }

    public function saveOutput(): string
    {
        $temp = $this->generateCsv();

        return $this->saveToS3($temp, 'Billing Discrepancies - '.date('Y-m-d').'.csv');
    }

    /**
     * Audits a single billing profile to check for discrepancies with the billing system.
     *
     * @param bool $rebuild rebuild the subscription if there are no discrepancies
     */
    public function audit(BillingProfile $billingProfile, bool $rebuild, ?Run $run = null): bool
    {
        try {
            // Retrieve the subscription state from the billing system
            $billingSystem = $this->billingSystemFactory->getForBillingProfile($billingProfile);
            $subscription = $billingSystem->getCurrentSubscription($billingProfile);

            // Generate the subscription items
            $subscriptionItems = $this->billingItemFactory->generateItems($billingProfile);

            // Validate that the billing system subscription matches the expected subscription
            $this->checkSubscriptionMatches($billingProfile, $subscriptionItems, $subscription, $run);

            // Rebuild the subscription when requested
            if ($rebuild) {
                $this->rebuildBillingProfile($billingProfile, $billingSystem, $run, $subscriptionItems);
            }
        } catch (BillingException $e) {
            $this->addDiscrepancy($run, $billingProfile, $e->getMessage());
        }

        return !isset($this->hasDiscrepancy[$billingProfile->id]);
    }

    /**
     * @param BillingSubscriptionItem[] $subscriptionItems
     */
    private function checkSubscriptionMatches(BillingProfile $billingProfile, array $subscriptionItems, BillingSystemSubscription $subscription, ?Run $run): void
    {
        // Calculate the expected total
        $expectedTotal = Money::zero('usd');
        foreach ($subscriptionItems as $subscriptionItem) {
            $expectedTotal = $expectedTotal->add($subscriptionItem->total);
        }

        // Check that the billing system total matches the expected total.
        // allow for a one cent difference due to rounding issues
        $difference = $expectedTotal->subtract($subscription->total);
        if ($difference->abs()->greaterThan(new Money('usd', 1))) {
            $this->addDiscrepancy($run, $billingProfile, 'Billing system total ('.$subscription->total.') does not match expected ('.$expectedTotal.')', $difference, $subscriptionItems);
        }

        // Check that the billing interval matches the expected billing interval.
        if (!$billingProfile->billing_interval) {
            $billingProfile->billing_interval = $subscription->billingInterval;
            $billingProfile->saveOrFail();
        } elseif ($billingProfile->billing_interval != $subscription->billingInterval) {
            $this->addDiscrepancy($run, $billingProfile, 'Billing interval on billing system ('.$subscription->billingInterval->name.') does not match expected ('.$billingProfile->billing_interval->name.')', $difference);
        }

        // Check that the subscription is not paused.
        if ($subscription->paused) {
            $this->addDiscrepancy($run, $billingProfile, 'Subscription is paused on billing system', $difference);
        }
    }

    /**
     * @param BillingSubscriptionItem[] $subscriptionItems
     */
    private function addDiscrepancy(?Run $run, BillingProfile $billingProfile, string $reason, ?Money $amount = null, array $subscriptionItems = []): void
    {
        if ($run) {
            $run->writeOutput('! '.$billingProfile->name.' (# '.$billingProfile->id.'): '.$reason);
            if ($amount && !$amount->isZero()) {
                $run->writeOutput('Discrepancy: '.$amount);
            }
        }

        $this->hasDiscrepancy[$billingProfile->id] = true;
        ++$this->numDiscrepancies;
        $this->discrepancies[] = [
            $billingProfile->id,
            $billingProfile->name,
            $billingProfile->billing_system,
            $billingProfile->stripe_customer,
            $billingProfile->invoiced_customer,
            $reason,
            $amount && !$amount->isZero() ? $amount->toDecimal() : '',
        ];

        foreach ($subscriptionItems as $subscriptionItem) {
            $this->discrepancies[] = [
                $billingProfile->id,
                $billingProfile->name,
                $billingProfile->billing_system,
                $billingProfile->stripe_customer,
                $billingProfile->invoiced_customer,
                '',
                '',
                $subscriptionItem->name,
                $subscriptionItem->description,
                $subscriptionItem->quantity,
                $subscriptionItem->price->toDecimal(),
                $subscriptionItem->total->toDecimal(),
                $subscriptionItem->customPricing ? 'Yes' : 'No',
            ];
        }
    }

    /**
     * @param BillingSubscriptionItem[] $subscriptionItems
     */
    private function rebuildBillingProfile(BillingProfile $billingProfile, BillingSystemInterface $billingSystem, ?Run $run, array $subscriptionItems): void
    {
        // If there are discrepancies or custom pricing then the subscription cannot be rebuilt
        if (isset($this->hasDiscrepancy[$billingProfile->id])) {
            return;
        }

        foreach ($subscriptionItems as $subscriptionItem) {
            if ($subscriptionItem->customPricing) {
                return;
            }
        }

        if ($run) {
            $run->writeOutput('Rebuilding subscription for billing profile # '.$billingProfile->id);
        }

        try {
            $billingSystem->updateSubscription($billingProfile, $subscriptionItems, false, CarbonImmutable::now());
        } catch (BillingException $e) {
            if ($run) {
                $run->writeOutput($e->getMessage());
            }
        }
    }

    /**
     * Persists data to S3 using a randomized filename.
     */
    private function saveToS3(File $tmpFile, string $filename): string
    {
        $key = strtolower(Utility::guid());

        $file = $this->s3FileCreator->create($this->bucket, $filename, $tmpFile->getFileName(), $key, [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'SourceFile' => $tmpFile->getFileName(),
            'ContentDisposition' => 'attachment; filename="'.$filename.'"',
        ]);

        return $file->url;
    }
}
