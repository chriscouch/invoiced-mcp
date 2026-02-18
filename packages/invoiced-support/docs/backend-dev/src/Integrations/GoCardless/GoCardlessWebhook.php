<?php

namespace App\Integrations\GoCardless;

use App\CashApplication\Models\Transaction;
use App\Companies\Models\Company;
use App\Core\Database\TransactionManager;
use App\Integrations\Interfaces\WebhookHandlerInterface;
use App\PaymentProcessing\Gateways\GoCardlessGateway;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Operations\UpdateChargeStatus;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Lock\LockFactory;

/**
 * Handles incoming GoCardless webhooks.
 */
class GoCardlessWebhook implements WebhookHandlerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    const LOCK_TTL = 2592000;

    public function __construct(
        private string $webhookSecret,
        private UpdateChargeStatus $updateChargeStatus,
        private LockFactory $lockFactory,
        private TransactionManager $transactionManager,
        private string $cacheNamespace = '',
    ) {
    }

    /**
     * Validates a webhook signature.
     */
    public function validateSignature(string $signature, string $payload): bool
    {
        if (!$signature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    //
    // WebhookHandlerInterface
    //

    public function shouldProcess(array &$event): bool
    {
        // verify the event / entity has not been processed before
        $key = $this->cacheNamespace.':gocardless_event.'.$event['id'];
        $lock = $this->lockFactory->createLock($key, self::LOCK_TTL, false);
        if (!$lock->acquire()) {
            return false;
        }

        // the webhook has already been verified in the
        // controller because gocardless signs the requests
        return true;
    }

    public function getCompanies(array $event): array
    {
        $id = $event['links']['organisation'];

        $merchantAccounts = MerchantAccount::queryWithoutMultitenancyUnsafe()
            ->where('gateway_id', $id)
            ->where('gateway', GoCardlessGateway::ID)
            ->where('deleted', false)
            ->all();

        $companies = [];
        foreach ($merchantAccounts as $merchantAccount) {
            $companies[] = $merchantAccount->tenant();
        }

        return $companies;
    }

    public function process(Company $company, array $event): void
    {
        $company->useTimezone();

        // generate the method name for the webhook handler
        // i.e. mandate cancelled -> mandate_cancelled
        $handler = strtolower($event['resource_type'].'_'.$event['action']);

        if (method_exists($this, $handler)) {
            $this->$handler($event);
        }
    }

    //
    // Mandate Events
    //

    public function mandates_active(array $event): void
    {
        $this->setMandateChargeable($event['links']['mandate'], true);
    }

    public function mandates_reinstated(array $event): void
    {
        $this->setMandateChargeable($event['links']['mandate'], true);
    }

    public function mandates_cancelled(array $event): void
    {
        $reason = $event['details']['description'];
        $this->setMandateChargeable($event['links']['mandate'], false, $reason);
    }

    public function mandates_failed(array $event): void
    {
        $reason = $event['details']['description'];
        $this->setMandateChargeable($event['links']['mandate'], false, $reason);
    }

    public function mandates_expired(array $event): void
    {
        $reason = $event['details']['description'];
        $this->setMandateChargeable($event['links']['mandate'], false, $reason);
    }

    public function mandates_replaced(array $event): void
    {
        $oldMandateId = $event['links']['mandate'];
        $newMandateId = $event['links']['new_mandate'];
        BankAccount::where('gateway_id', $oldMandateId)->set([
            'gateway_id' => $newMandateId,
            'chargeable' => true,
            'verified' => true,
        ]);
    }

    public function mandates_resubmission_requested(array $event): void
    {
        $this->setMandateChargeable($event['links']['mandate'], true, null, false);
    }

    /**
     * Updates all the bank accounts associated with a mandate.
     */
    private function setMandateChargeable(string $mandateId, bool $chargable, ?string $failureReason = null, bool $verified = true): void
    {
        // look up any matching bank accounts
        // and un-mark them as chargeable
        $this->transactionManager->perform(function () use ($mandateId, $chargable, $failureReason, $verified) {
            $bankAccounts = BankAccount::where('gateway_id', $mandateId)->all();
            foreach ($bankAccounts as $bankAccount) {
                if ($bankAccount->chargeable == $chargable && $bankAccount->verified) {
                    continue;
                }

                if (false !== $failureReason) {
                    $bankAccount->failure_reason = $failureReason;
                }

                $bankAccount->verified = $verified;

                if ($chargable) {
                    $bankAccount->chargeable = true;
                    $bankAccount->saveOrFail();
                } else {
                    $bankAccount->delete();
                }
            }
        });
    }

    //
    // Settled Payment Events
    //

    public function payments_charged_back(array $event): void
    {
        $this->updatePaymentStatus($event['links']['payment']);
    }

    public function payments_late_failure_settled(array $event): void
    {
        $this->updatePaymentStatus($event['links']['payment']);
    }

    public function payments_chargeback_settled(array $event): void
    {
        $this->updatePaymentStatus($event['links']['payment']);
    }

    /**
     * Updates the status of a GoCardless payment that might have already
     * settled (status=succeeded).
     */
    private function updatePaymentStatus(string $paymentId): void
    {
        $charge = Charge::where('gateway', GoCardlessGateway::ID)
            ->where('gateway_id', $paymentId)
            ->where('status', Transaction::STATUS_FAILED, '<>')
            ->oneOrNull();

        if ($charge instanceof Charge) {
            $this->updateChargeStatus->update($charge);
        }
    }
}
