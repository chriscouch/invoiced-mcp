<?php

namespace App\EntryPoint\CronJob;

use App\Core\Multitenant\TenantContext;
use App\Core\Utils\Enums\ObjectType;
use App\PaymentProcessing\Enums\DisputeStatus;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\Dispute;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class DisputesResolutionCronJob extends AbstractTaskQueueCronJob
{
    private const int DEFAULT_TIMEOUT_DAYS = 9; // Minimum period for disputes

    private const array DISPUTE_RESOLUTION_STATUSES = [
        DisputeStatus::Unresponded->value =>  DisputeStatus::Lost->value, // responded_by_customer case
        DisputeStatus::Pending->value =>  DisputeStatus::Won->value, // chargeback_reversed case
        DisputeStatus::Responded->value =>  DisputeStatus::Won->value, // chargeback_reversed case
        DisputeStatus::Expired->value =>  DisputeStatus::Lost->value,
        DisputeStatus::Undefended->value =>  DisputeStatus::Lost->value,
    ];

    private const array DISPUTE_RESOLUTION_ACTIONS = [
        DisputeStatus::Lost->value => [
            'visa' => 9,
            'mastercard' => 40,
            'amex' => 14,
            'diners' => 25,
            'discover' => 25,
            'cup' => 30,
            'jcb' => 40,
        ],
        DisputeStatus::Won->value => [
            'visa' => 60,
            'mastercard' => 70,
            'amex' => 50,
            'diners' => 60,
            'discover' => 80,
            'cup' => 20,
            'jcb' => 35,
        ],
    ];

    public function __construct(
        private TenantContext $tenant,
    ) {
    }

    public function getTasks(): iterable
    {
        return Dispute::queryWithoutMultitenancyUnsafe()
            ->where('status IN ('. implode(",", array_keys(self::DISPUTE_RESOLUTION_STATUSES)).')')
            ->where('updated_at', CarbonImmutable::now()->subDays(self::DEFAULT_TIMEOUT_DAYS)->toDateTimeString(), '<=')
            ->all();
    }

    /** @param Dispute $task */
    public function runTask(mixed $task): bool
    {
        $company = $task->tenant();
        $this->tenant->set($company);

        if ($task->charge->payment_source_type === ObjectType::BankAccount->typeName()) {
            //bank accounts chargebacks should not be part of disputes
            $task->delete();

            return true;
        }

        /** @var ?Card $card */
        $card = $task->charge->payment_source;
        if ($card === null) {
            return true; // Skip if no card found
        }

        $cardBrand = $this->getCardBrand($card);
        $daysSinceUpdate = CarbonImmutable::now()->diffInDays(Carbon::createFromTimestamp($task->updated_at)->toDateTimeImmutable());
        $timeoutDays = $this->getTimeoutDaysForDispute($task, $cardBrand);

        if ($daysSinceUpdate < $timeoutDays) {
            return true; // No dispute to resolve yet
        }

        return $this->resolveDispute($task);
    }

    private function getCardBrand(?Card $card): string
    {
        if ($card instanceof Card) {
            return strtolower($card->brand ?? 'visa');
        }

        return 'visa';
    }

    private function getTimeoutDaysForDispute(Dispute $dispute, string $cardBrand): int
    {
        $resolutionStatus = self::DISPUTE_RESOLUTION_STATUSES[$dispute->status->value];

        return self::DISPUTE_RESOLUTION_ACTIONS[$resolutionStatus][$cardBrand] ?? self::DEFAULT_TIMEOUT_DAYS;
    }

    private function resolveDispute(Dispute $dispute): bool
    {
        $dispute->status = DisputeStatus::from(self::DISPUTE_RESOLUTION_STATUSES[$dispute->status->value]);
        $dispute->save();

        return true;
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }
}