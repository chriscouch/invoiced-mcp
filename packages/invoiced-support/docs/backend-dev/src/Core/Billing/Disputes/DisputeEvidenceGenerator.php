<?php

namespace App\Core\Billing\Disputes;

use App\AccountsReceivable\Models\Invoice;
use App\Companies\Enums\TaxIdType;
use App\Companies\Models\Company;
use App\Companies\Models\CompanyTaxId;
use App\Core\Authentication\Models\AccountSecurityEvent;
use App\Core\Authentication\Models\User;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Files\Interfaces\FileCreatorInterface;
use App\Core\I18n\MoneyFormatter;
use App\Core\Multitenant\TenantContext;
use App\Core\Pdf\Pdf;
use App\Core\Pdf\PdfMerger;
use App\Core\Utils\InfuseUtility as U;
use App\Core\Utils\InfuseUtility as Utility;
use App\Core\Utils\ModelUtility;
use App\Core\Utils\ZipUtil;
use mikehaertl\tmp\File;
use Twig\Environment;

class DisputeEvidenceGenerator
{
    private const ACTIVITY_LOG_LIMIT = 25;

    public function __construct(
        private Environment $twig,
        private TenantContext $tenant,
        private readonly FileCreatorInterface $s3FileCreator,
        private string $bucket,
        private string $projectDir
    ) {
    }

    /**
     * Generates evidence and uploads to a downloadable URL in S3.
     *
     * @throws BillingException
     */
    public function generateToUrl(BillingProfile $billingProfile): string
    {
        // generate the evidence
        $evidence = $this->generate($billingProfile);

        // build a combined file of all evidence
        $combinedFile = $this->makeCombinedFile($evidence, $billingProfile);

        // build a list of files for the evidence
        $files = [$combinedFile];

        foreach ($evidence as $k => $v) {
            if ($v instanceof File) {
                $newFilename = $k.'.pdf';
                rename($v->getFileName(), $newFilename);
                $files[] = $newFilename;
            } elseif (is_string($v)) {
                $newFilename = $k.'.txt';
                file_put_contents($newFilename, $v);
                $files[] = $newFilename;
            }
        }

        // save all the evidence to a zip file
        $tempDir = $this->projectDir.'/var/exports';
        @mkdir($tempDir);
        $zipFilename = $tempDir.'/'.strtolower(U::guid()).'.zip';
        $zipFile = ZipUtil::createZip($files, $tempDir, '', $zipFilename);
        if (!$zipFile) {
            throw new BillingException('Could not create ZIP');
        }

        // upload to s3
        $url = $this->saveToS3($zipFile, 'Dispute Evidence - '.$billingProfile->name);

        // clean up files
        @unlink($zipFilename);
        foreach ($files as $filename) {
            @unlink($filename);
        }

        return $url;
    }

    public function generate(BillingProfile $billingProfile): array
    {
        $companies = $this->getCompanies($billingProfile);
        $users = $this->getUsers($companies);

        return [
            'access_activity_log' => $this->buildActivityLog($users),
            'billing_address' => $this->buildCustomerBillingAddress($companies),
            'cancellation_policy' => $this->buildCancellationPolicy(), // file
            'cancellation_policy_disclosure' => 'Our cancellation policy is disclosed to customers in the Terms of Service (https://www.invoiced.com/legal/terms), on the subscription purchase page, and in our frequently asked questions (FAQs).',
            'customer_email_address' => $this->buildCustomerEmailAddress($companies),
            'customer_name' => $this->buildCustomerName($companies, $users, $billingProfile),
            'customer_purchase_ip' => $this->buildCustomerIp($users),
            'refund_policy' => $this->buildRefundPolicy(), // file
            'refund_policy_disclosure' => 'Our refund policy is disclosed to customers in the Terms of Service (https://www.invoiced.com/legal/terms) and frequently asked questions (FAQs).',
            'refund_refusal_explanation' => 'The customer did not request a refund, per the records in our support ticketing system. Furthermore, each payment is prepaid for the upcoming billing period and is non-refundable. Subscriptions may be canceled at any time according to our cancellation policy.',
            'product_description' => 'Invoiced is an online invoicing system. Our service allows businesses to track and send invoices to clients, automate reminders to non-paying customers, and accept payments online. Our customers have the option to purchase a monthly or yearly subscription in order to get access to Invoiced.',
            'service_date' => date('F j, Y', $billingProfile->created_at),
            'service_documentation' => $this->buildServiceDocumentation($companies, $users), // file
        ];
    }

    /**
     * @return Company[]
     */
    private function getCompanies(BillingProfile $billingProfile): array
    {
        return Company::where('billing_profile_id', $billingProfile)
            ->first(100);
    }

    /**
     * @param Company[] $companies
     *
     * @return User[]
     */
    private function getUsers(array $companies): array
    {
        if (!$companies) {
            return [];
        }

        $companyIds = array_map(fn ($company) => $company->id(), $companies);

        $query = User::where('(EXISTS (SELECT 1 FROM Members WHERE user_id=Users.id AND tenant_id IN ('.implode(',', $companyIds).') AND expires=0) OR EXISTS (SELECT 1 FROM Companies WHERE creator_id=Users.id AND id IN ('.implode(',', $companyIds).')))');

        return ModelUtility::getAllModels($query);
    }

    /**
     * @param User[] $users
     */
    public function buildActivityLog(array $users): string
    {
        $lines = [];

        foreach ($users as $user) {
            if (count($lines) > self::ACTIVITY_LOG_LIMIT) {
                break;
            }

            $name = $user->name(true).' ('.$user->email.')';
            $signInActivity = AccountSecurityEvent::where('user_id', $user->id())
                ->where('type', AccountSecurityEvent::LOGIN)
                ->sort('created_at DESC')
                ->first(5);
            foreach ($signInActivity as $activity) {
                $lines[] = "$name signed in on ".date('F j, Y g:i a', $activity->created_at).($activity->ip ? " from IP address {$activity->ip}" : '');
            }
        }

        $lines = array_slice($lines, 0, self::ACTIVITY_LOG_LIMIT);

        if (count($lines) > 0) {
            $lines[] = '* Only showing most recent activity. Older activity truncated for brevity.';
        }

        return join("\n", $lines);
    }

    /**
     * @param User[] $users
     */
    public function buildCustomerIp(array $users): ?string
    {
        foreach ($users as $user) {
            return $user->ip;
        }

        return null;
    }

    /**
     * @param Company[] $companies
     */
    public function buildCustomerEmailAddress(array $companies): ?string
    {
        foreach ($companies as $company) {
            if ($company->email) {
                return $company->email;
            }
        }

        return null;
    }

    /**
     * @param Company[] $companies
     * @param User[]    $users
     */
    public function buildCustomerName(array $companies, array $users, BillingProfile $billingProfile): string
    {
        $name = $billingProfile->name;

        foreach ($companies as $company) {
            if ($company->name) {
                $name = $company->name;
                break;
            }
        }

        if (count($users) > 0) {
            $name .= ' ('.$users[0]->first_name.' '.$users[0]->last_name.')';
        }

        return $name;
    }

    /**
     * @param Company[] $companies
     */
    public function buildCustomerBillingAddress(array $companies): ?string
    {
        foreach ($companies as $company) {
            if ($company->address1) {
                return str_replace("\n", ', ', $company->address(true, true));
            }
        }

        return null;
    }

    /**
     * @param Company[] $companies
     * @param User[]    $users
     */
    public function buildServiceDocumentation(array $companies, array $users): File
    {
        $currency = 'usd';
        $numInvoices = 0;
        $invoiceAmount = 0;
        $invoices = [];
        foreach ($companies as $company) {
            $this->tenant->set($company);

            $query = Invoice::queryWithTenant($company);
            $numInvoices += $query->count();
            $invoiceAmount += $query->sum('total');
            $currency = $company->currency;

            if (count($invoices) > self::ACTIVITY_LOG_LIMIT) {
                continue;
            }

            foreach ($query->with('customer')->first(100) as $invoice) {
                $invoices[] = [
                    'number' => $invoice->number,
                    'customerName' => $invoice->customer()->name,
                    'total' => MoneyFormatter::get()->currencyFormat($invoice->total, $invoice->currency),
                ];
            }
        }

        $company = null;
        if (count($companies) > 0) {
            $taxId = CompanyTaxId::queryWithTenant($companies[0])
                ->where('tax_id_type', TaxIdType::EIN->value)
                ->oneOrNull();

            $company = [
                'name' => $companies[0]->name,
                'address' => str_replace("\n", ', ', $companies[0]->address(true, true)),
                'email' => $companies[0]->email,
                'phone' => $companies[0]->phone,
                'website' => $companies[0]->website,
                'industry' => $companies[0]->industry,
                'tax_id' => $taxId?->tax_id ?? $companies[0]->tax_id,
                'url' => $companies[0]->url,
                'created_at' => $companies[0]->created_at,
            ];
        }

        $html = $this->twig->render('disputes/service_documentation.twig', [
            'company' => $company,
            'numInvoices' => $numInvoices,
            'invoiceAmount' => MoneyFormatter::get()->currencyFormat($invoiceAmount, $currency),
            'invoices' => array_slice($invoices, 0, self::ACTIVITY_LOG_LIMIT),
            'users' => $users,
        ]);

        return $this->makePdf($html);
    }

    public function buildCancellationPolicy(): File
    {
        return $this->makePdf($this->twig->render('disputes/cancellation_policy.twig'));
    }

    public function buildRefundPolicy(): File
    {
        return $this->makePdf($this->twig->render('disputes/refund_policy.twig'));
    }

    private function makeCombinedFile(array $evidence, BillingProfile $billingProfile): string
    {
        $remainingEvidence = $this->makePdf($this->twig->render('disputes/combined.twig', [
            'evidence' => $evidence,
        ]));
        $pdfs = [
            $evidence['service_documentation'],
            $remainingEvidence,
            $evidence['cancellation_policy'],
            $evidence['refund_policy'],
        ];

        $pdf = (new PdfMerger())->merge($pdfs);
        $newFilename = 'Dispute Evidence - '.$billingProfile->name.'.pdf';
        rename($pdf->getFileName(), $newFilename);

        return $newFilename;
    }

    private function makePdf(string $html): File
    {
        $pdf = Pdf::make();
        $pdf->addPage($html);

        return $pdf->getTempFile();
    }

    /**
     * Persists data to S3 using a randomized filename.
     */
    private function saveToS3(string $tmpFilename, string $filename): string
    {
        $key = strtolower(Utility::guid());

        $file = $this->s3FileCreator->create($this->bucket, $tmpFilename, $filename, $key, [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'SourceFile' => $tmpFilename,
            'ContentDisposition' => 'attachment; filename="'.$filename.'"',
        ]);

        return $file->url;
    }
}
