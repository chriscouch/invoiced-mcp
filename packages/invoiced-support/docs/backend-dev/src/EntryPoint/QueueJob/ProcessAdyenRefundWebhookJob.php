<?php

namespace App\EntryPoint\QueueJob;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Integrations\Adyen\Enums\RefundEvent;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Refund;
use App\PaymentProcessing\ValueObjects\RefundValueObject;

class ProcessAdyenRefundWebhookJob extends AbstractResqueJob implements MaxConcurrencyInterface, TenantAwareQueueJobInterface
{
    public function __construct(
        private readonly AdyenGateway $gateway,
    ) {
    }

    public function perform(): void
    {
        // messy hack to convert an object to an array
        $data = json_decode((string) json_encode($this->args['event']), true);

        $reference = $data['pspReference'];

        /** @var ?Refund $refund */
        $refund = Refund::where('gateway', AdyenGateway::ID)
            ->where('gateway_id', $reference)
            ->where('status', RefundValueObject::PENDING)
            ->oneOrNull();

        if (!$refund) {
            return;
        }

        // retry successful
        if ('true' === $data['success']) {
            $refund->status = $data['eventCode'] === RefundEvent::REFUND_FAILED->value ? RefundValueObject::FAILED : RefundValueObject::SUCCEEDED;
            $refund->saveOrFail();

            return;
        }

        // retry refund
        // we tried to cancel and faild, so we try to credit
        if ($data['eventCode'] === RefundEvent::CANCELLATION->value) {
            $merchantAccount = $refund->charge->merchant_account;
            if (!$merchantAccount) {
                $refund->status = RefundValueObject::FAILED;
                $refund->saveOrFail();

                return;
            }

            $amount = Money::fromDecimal($refund->currency, $refund->amount);
            try {
                $pspReference = $this->gateway->credit($merchantAccount->gateway_id, $merchantAccount, $data['merchantReference'], $refund->charge->gateway_id, $amount);
                $refund->gateway_id = $pspReference;
            } catch (RefundException) {
                $refund->status = RefundValueObject::FAILED;
            }

            $refund->saveOrFail();

            return;
        }

        // fail if not successful
        $refund->status = RefundValueObject::FAILED;
        $refund->saveOrFail();
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'adyen_refund_webhook:'.$args['tenant_id'];
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
