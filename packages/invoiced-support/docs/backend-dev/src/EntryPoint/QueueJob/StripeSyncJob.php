<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\CustomerPortal\Exceptions\PaymentLinkException;
use App\Integrations\Stripe\ReconcileStripePaymentFlow;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Exceptions\TransactionStatusException;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class StripeSyncJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, MaxConcurrencyInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly ReconcileStripePaymentFlow $reconcileStripePaymentFlow,
    ) {
    }
    const int MAX_DAYS = 30;

    public function perform(): void
    {
        /** @var MerchantAccount|null $account */
        $account = MerchantAccount::find($this->args['merchantAccountId']);
        if (!$account) {
            return;
        }

        /** @var PaymentFlow[] $flows */
        $flows = PaymentFlow::where('gateway', StripeGateway::ID)
            ->where('status', [PaymentFlowStatus::Processing->value, PaymentFlowStatus::ActionRequired->value, PaymentFlowStatus::CollectPaymentDetails->value])
            ->where('merchant_account_id', $account)
            ->where('created_at', CarbonImmutable::now()->subDays(self::MAX_DAYS), '>')
            ->all();

        foreach ($flows as $flow) {
            try {
                $this->reconcileStripePaymentFlow->reconcile($flow);
            } catch (FormException|PaymentLinkException|ChargeException $e) {
                $this->logger->error($e->getMessage());
            } catch (TransactionStatusException $e) {
                $this->logger->error('Stripe API Failure: '.$e->getMessage());
            }
        }
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 5;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'stripe_sync:'.$args['merchantAccountId'];
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 1800;
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return false;
    }
}
