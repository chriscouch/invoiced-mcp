<?php

namespace App\EntryPoint\QueueJob;

use App\CashApplication\Libs\DuplicatePaymentsReconciler;
use App\CashApplication\Models\Payment;
use App\Core\Database\TransactionManager;
use App\Core\Files\Exception\UploadException;
use App\Core\Files\Libs\AttachmentUploader;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Exception\ModelException;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Utils\InfuseUtility as Utility;
use App\Integrations\EarthClassMail\EarthClassMailClient;
use App\Integrations\EarthClassMail\Models\EarthClassMailAccount;
use App\Integrations\EarthClassMail\ValueObjects\Check;
use App\Integrations\EarthClassMail\ValueObjects\Piece;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Libs\IntegrationFactory;
use App\Integrations\Services\EarthClassMail;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\PaymentProcessing\Models\PaymentMethod;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

/**
 * Retrieves all the checks for an Earth Class Mail connection
 * and creates payments.
 */
class EarthClassMailSyncJob extends AbstractResqueJob implements LoggerAwareInterface, TenantAwareQueueJobInterface
{
    use LoggerAwareTrait;

    const BATCH_SIZE = 1000;
    private string $tempDir;

    public function __construct(
        private readonly IntegrationFactory $integrations,
        private readonly EarthClassMailClient $client,
        private readonly AttachmentUploader $attachmentUploader,
        private readonly DuplicatePaymentsReconciler $reconciler,
        string $projectDir,
        private readonly TransactionManager $transactionManager,
        private readonly NotificationSpool $notificationSpool,
        private TenantContext $tenant,
    ) {
        $this->tempDir = $projectDir.'/var/uploads/';
        if (!is_dir($this->tempDir)) {
            @mkdir($this->tempDir, 0774);
        }
    }

    public function perform(): void
    {
        /** @var EarthClassMail $integration */
        $integration = $this->integrations->get(IntegrationType::EarthClassMail, $this->tenant->get());
        $account = $integration->getAccount();
        if (!$account) {
            return;
        }

        $this->syncAccount($account);
    }

    /**
     * Syncs an Earth Class Mail account.
     */
    private function syncAccount(EarthClassMailAccount $account): void
    {
        $count = 0;
        $start = CarbonImmutable::now();

        try {
            $page = 1;
            do {
                $dateFrom = null;
                if ($lastSync = $account->last_retrieved_data_at) {
                    // Get checks starting from 30 days prior to last sync time.
                    //
                    // Checks have a `created_at` time, but also a non-filterable extraction
                    // time at which the data becomes available. This extraction time is said
                    // by ECM to be "as late as the next day" (See INVD-2510). However, from
                    // experience this can be longer than 2 days. In order to be certain we
                    // do not miss any checks we grab data from the prior 30 days. This is ok
                    // because there is a duplicate prevention check for previously imported
                    // payments, even if the payment is voided.
                    $dateFrom = CarbonImmutable::createFromTimestamp($lastSync)->subDays(30);
                }

                $pieces = $this->client->getDeposits($account, $account->inbox_id, $dateFrom, $page);
                $count += $this->saveChecksAsPayments($pieces);
                ++$page;
            } while ($this->client->hasMoreDeposits());

            $account->last_retrieved_data_at = $start->getTimestamp();
            $account->saveOrFail();
        } catch (IntegrationApiException $e) {
            // Log if this is not an invalid API key response
            if (401 != $e->getCode()) {
                $this->logger->warning('Error when importing check payments from ECM', ['exception' => $e]);
            }
        }
    }

    /**
     * @param Piece[] $pieces
     */
    private function saveChecksAsPayments(array $pieces): int
    {
        $count = 0;

        foreach ($pieces as $piece) {
            foreach ($piece->checks as $check) {
                $existingPayments = Payment::where('source', Payment::SOURCE_CHECK_LOCKBOX)
                    ->where('external_id', $check->id)
                    ->count();
                if ($existingPayments > 0) {
                    continue;
                }

                try {
                    $this->createPayment($piece, $check);
                    ++$count;
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return $count;
    }

    /**
     * @throws ModelException
     * @throws UploadException
     */
    private function createPayment(Piece $piece, Check $check): void
    {
        // Upload all scanned images to Invoiced as attachments
        $files = [];
        foreach ($piece->getMedia() as $media) {
            if ($checkImage = @file_get_contents($media->url)) {
                $tempName = Utility::guid(false);
                file_put_contents($this->tempDir.$tempName, $checkImage);
                $fileName = 'Check #'.$check->check_number;
                $fileType = explode('/', $media->content_type);
                $files[] = $this->attachmentUploader->upload($this->tempDir.$tempName, $fileName.'.'.$fileType[1]);
            }
        }

        $payment = new Payment();
        // This integration is currently only available in the US
        $amount = new Money('usd', $check->amount_in_cents);
        $payment->amount = $amount->toDecimal();
        $payment->currency = 'usd';
        $time = strtotime($piece->created_at);
        $payment->date = $time ?: time();
        $payment->method = PaymentMethod::CHECK;
        $payment->reference = $check->check_number;
        $payment->source = Payment::SOURCE_CHECK_LOCKBOX;
        $payment->external_id = $check->id;

        $this->transactionManager->perform(function () use ($payment, $files) {
            if ($existingPayment = $this->reconciler->detectDuplicatePayment($payment)) {
                $this->reconciler->mergeDuplicatePayments($existingPayment, $payment->toArray());
            } else {
                $payment->saveOrFail();
                $this->notificationSpool->spool(NotificationEventType::LockboxCheckReceived, $payment->tenant_id, $payment->id);
            }

            foreach ($files as $file) {
                $this->attachmentUploader->attachToObject($payment, $file);
            }
        });
    }
}
