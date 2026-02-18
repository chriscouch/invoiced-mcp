<?php

namespace App\Core\Billing\Disputes;

use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use App\Integrations\Stripe\HasStripeClientTrait;
use Stripe\Dispute;
use mikehaertl\tmp\File as TmpFile;
use Stripe\Exception\ExceptionInterface as StripeError;

class StripeDisputeHandler
{
    use HasStripeClientTrait;

    public function __construct(
        string $stripeBillingSecret,
        private DisputeEvidenceGenerator $disputeEvidenceGenerator,
    ) {
        $this->stripeSecret = $stripeBillingSecret;
    }

    public function getStripeDispute(string $stripeDisputeId): Dispute
    {
        return $this->getStripe()->disputes->retrieve($stripeDisputeId);
    }

    /**
     * @throws BillingException
     */
    public function updateStripeDispute(Dispute $dispute, BillingProfile $billingProfile): void
    {
        $evidence = $this->disputeEvidenceGenerator->generate($billingProfile);

        try {
            $dispute->evidence = [/* @phpstan-ignore-line */
                'access_activity_log' => $evidence['access_activity_log'],
                'billing_address' => $evidence['billing_address'],
                'cancellation_policy' => $this->uploadFile($evidence['cancellation_policy']),
                'cancellation_policy_disclosure' => $evidence['cancellation_policy_disclosure'],
                'customer_email_address' => $evidence['customer_email_address'],
                'customer_name' => $evidence['customer_name'],
                'customer_purchase_ip' => $evidence['customer_purchase_ip'],
                'product_description' => $evidence['product_description'],
                'refund_policy' => $this->uploadFile($evidence['refund_policy']),
                'refund_policy_disclosure' => $evidence['refund_policy_disclosure'],
                'refund_refusal_explanation' => $evidence['refund_refusal_explanation'],
                'service_date' => $evidence['service_date'],
                'service_documentation' => $this->uploadFile($evidence['service_documentation']),
            ];
            $dispute->save();
        } catch (StripeError $e) {
            throw new BillingException($e->getMessage());
        }
    }

    private function uploadFile(TmpFile $file): string
    {
        $fp = fopen($file, 'r');
        $file = $this->getStripe()->files->create([
            'file' => $fp,
            'purpose' => 'dispute_evidence',
        ]);

        return $file->id;
    }
}
