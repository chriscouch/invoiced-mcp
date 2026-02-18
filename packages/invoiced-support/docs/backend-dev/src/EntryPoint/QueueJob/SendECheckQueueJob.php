<?php

namespace App\EntryPoint\QueueJob;

use App\AccountsPayable\Libs\ECheckPdf;
use App\AccountsPayable\Models\ECheck;
use App\AccountsPayable\ValueObjects\CheckPdfVariables;
use App\Core\Files\Models\File;
use App\Core\Files\Models\VendorPaymentAttachment;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Mailer\Mailer;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Core\S3ProxyFactory;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\AppUrl;
use App\Core\Utils\Compression;
use App\Core\Utils\InfuseUtility as Utility;
use Aws\S3\Exception\S3Exception;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use RuntimeException;

/**
 * Sends E-check for appropriate bill.
 */
class SendECheckQueueJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, StatsdAwareInterface, LoggerAwareInterface, MaxConcurrencyInterface
{
    use StatsdAwareTrait;
    use LoggerAwareTrait;

    private mixed $s3;

    public function __construct(
        private readonly string $environment,
        private readonly string $bucket,
        private string $bucketRegion,
        private readonly Mailer $mailer,
        private readonly ECheckPdf $checkPdf,
        private string $filesUrl,
        S3ProxyFactory $s3Factory,
    ) {
        $this->s3 = $s3Factory->build();
    }

    public function perform(): void
    {
        $checkId = $this->args['check_id'];
        $eCheck = ECheck::findOrFail($checkId);
        $payment = $eCheck->payment;
        $vendor = $payment->vendor;
        $customer = $payment->tenant();
        $account = $eCheck->account;

        $balances = new CheckPdfVariables(Money::fromDecimal($payment->currency, $eCheck->amount));
        $address = $vendor->getVendorAddress();

        $bills = array_map(function ($item) use ($customer) {
            $bill = $item->bill;
            if (!$bill) {
                throw new RuntimeException('Bill not found');
            }

            return [
                'bill_id' => $bill->id,
                'number' => $bill->number,
                'amount' => $item->amount,
                'date' => $bill->date->format($customer->date_format),
            ];
        }, $payment->getItems());

        $vars = array_merge($balances->jsonSerialize(), [
            'vendor_name' => $vendor->name,
            'customer_name' => $customer->name,
            'vendor_id' => $vendor->id,
            'routing_number' => $account->routing_number,
            'account_number' => $account->account_number,
            'signature' => $account->signature,
            'check_number' => $eCheck->check_number,
            'currency' => $payment->currency,
            'date' => CarbonImmutable::now()->getTimestamp(),
            'bills' => $bills,
        ], $address);

        $this->checkPdf->setParameters([$vars]);
        $pdf = $this->checkPdf->build('en_US');
        $key = strtolower(Utility::guid());

        try {
            $this->s3->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => Compression::compress($pdf),
            ]);
        } catch (S3Exception $e) {
            $this->logger->error('Unable to upload e-check', ['exception' => $e]);

            $this->statsd->increment('e_check.error');

            return;
        }

        $file = new File();
        $file->tenant_id = $customer->tenant()->id;
        $file->name = "E-Check for {$vendor->name} ({$payment->number}).pdf";
        $file->size = strlen($pdf);
        $file->type = 'application/pdf';
        $file->url = $this->filesUrl . '/' . $key;
        $file->bucket_name = $this->bucket;
        $file->bucket_region = $this->bucketRegion;
        $file->s3_environment = $this->environment;
        $file->key = $key;
        $file->saveOrFail();

        $attachment = new VendorPaymentAttachment();
        $attachment->vendor_payment = $eCheck->payment;
        $attachment->file = $file;
        $attachment->saveOrFail();

        $to = ['email' => $eCheck->email, 'name' => $vendor->name];
        $from = $customer->email;
        // Send an email
        $this->mailer->send([
            'from_email' => $from,
            'to' => [$to],
            'subject' => 'You received E-Check from '.$customer->name,
        ], 'e-check', [
            'href' => AppUrl::get()->build().'/checks/'.$eCheck->hash,
            'vendor_name' => $vendor->name,
            'customer_name' => $customer->name,
        ]);

        $this->statsd->increment('e_check.sent');
    }

    public static function getMaxConcurrency(array $args): int
    {
        // 20 jobs for all accounts.
        return 20;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'send_e_check';
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 60; // 1 minute
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }
}
