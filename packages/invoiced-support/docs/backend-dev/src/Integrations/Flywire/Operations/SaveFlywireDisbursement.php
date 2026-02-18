<?php

namespace App\Integrations\Flywire\Operations;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Models\FlywireDisbursement;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\Integrations\Flywire\Models\FlywirePayout;
use App\Integrations\Flywire\Models\FlywireRefund;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class SaveFlywireDisbursement implements LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    public function __construct(
        private readonly FlywirePrivateClient $client,
    ) {
    }

    /**
     * Syncs a single Payment from Flywire to our database.
     *
     * @throws IntegrationApiException
     */
    public function sync(array $response, bool $forceUpdate = false): void
    {
        $disbursement = $this->saveDisbursement($response);
        $this->syncPayouts($disbursement, $response, $forceUpdate);
        $this->syncRefunds($disbursement, $response, $forceUpdate);
    }

    private function saveDisbursement(array $response): FlywireDisbursement
    {
        $disbursement = FlywireDisbursement::where('disbursement_id', $response['reference'])->oneOrNull() ?? new FlywireDisbursement();
        $disbursement->disbursement_id = $response['reference'];
        $disbursement->status_text = $response['status'];
        $disbursement->recipient_id = $response['destination_code'];
        $disbursement->delivered_at = $response['delivered_at'] ? new CarbonImmutable($response['delivered_at']) : null;
        $disbursement->bank_account_number = $response['bank_account_number'];
        $disbursement->setAmount(new Money($response['amount']['currency']['code'], $response['amount']['value']));
        $disbursement->saveOrFail();

        return $disbursement;
    }

    private function syncPayouts(FlywireDisbursement $disbursement, array $response, bool $forceUpdate): void
    {
        /** @var FlywirePayout[] $payouts */
        $payouts = FlywirePayout::where('disbursement_id', $disbursement->id)
            ->with('payment')
            ->all();

        // all the payments already synced
        if (!$forceUpdate && count($payouts) === (int) $response['number_of_payments']) {
            return;
        }

        $payoutsMapped = [];
        foreach ($payouts as $payout) {
            $payoutsMapped[$payout->payout_id] = $payout;
        }

        foreach ($this->client->getDisbursementPayouts($response['reference']) as $payoutResponse) {
            $payout = $payoutsMapped[$payoutResponse['reference']] ?? new FlywirePayout();
            $payment = $payout->payment ?? FlywirePayment::where('payment_id', $payoutResponse['payment_id'])
                ->oneOrNull();
            if (!$payment) {
                continue;
            }

            $payout->payout_id = $payoutResponse['reference'];
            $payout->disbursement = $disbursement;
            $payout->payment = $payment;
            $payout->status_text = $payoutResponse['status'];
            $payout->setAmount(new Money($payoutResponse['currency']['code'], $payoutResponse['amount']));
            $payout->saveOrFail();
        }
    }

    private function syncRefunds(FlywireDisbursement $disbursement, array $response, bool $forceUpdate): void
    {
        /** @var FlywireRefund[] $refunds */
        $refunds = FlywireRefund::where('disbursement_id', $disbursement->id)
            ->all();

        // all the payments already synced
        if (!$forceUpdate && count($refunds) === (int) $response['number_of_refunds']) {
            return;
        }

        $refundsMapped = [];
        foreach ($refunds as $refund) {
            $refundsMapped[$refund->refund_id] = $refund;
        }

        foreach ($this->client->getDisbursementRefunds($response['reference'], $response['destination_code']) as $refundResponse) {
            // we already have refund save
            if (isset($refundsMapped[$refundResponse['id']])) {
                continue;
            }

            /** @var ?FlywireRefund $refund */
            $refund = FlywireRefund::where('refund_id', $refundResponse['id'])
                ->oneOrNull();
            if (!$refund) {
                continue;
            }

            $refund->disbursement = $disbursement;
            $refund->saveOrFail();
        }
    }
}
