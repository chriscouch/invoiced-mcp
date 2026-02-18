<?php

namespace App\Integrations\Adyen\Operations;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Mailer\Mailer;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Adyen\Enums\ChargebackEvent;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Models\Dispute;
use App\PaymentProcessing\Models\DisputeFee;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class SaveAdyenDisputeFee implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly AdyenClient $adyenClient,
        private readonly bool $adyenLiveMode,
        private Mailer $mailer,
    ) {
    }

    public function save(Dispute $dispute, ChargebackEvent $event): void
    {
        if (!$expectedFees = $event->expectedFees()) {
            return;
        }

        $feesApplied = DisputeFee::where('dispute_id', $dispute->id)
            ->where('success', 1)
            ->count();

        if ($feesApplied >= $expectedFees) {
            return;
        }

        $merchantAccount = $dispute->charge->merchant_account;
        if (!$merchantAccount) {
            return;
        }

        if (!$account = AdyenConfiguration::getLiableAccount($this->adyenLiveMode)) {
            $this->logger->error('Adyen dispute fee save operation error');

            return;
        }

        $adyenAccount = AdyenAccount::one();
        $pricingConfig = AdyenConfiguration::getPricingForAccount($this->adyenLiveMode, $adyenAccount);

        $disputeFee = new DisputeFee();
        $disputeFee->dispute = $dispute;
        $disputeFee->amount = $pricingConfig->chargeback_fee;
        $currency = $dispute->currency;
        $disputeFee->currency = $currency;

        $balanceAccountId = $merchantAccount->toGatewayConfiguration()->credentials->balance_account ?? '';

        try {
            $data = $this->adyenClient->makeTransfer([
                'amount' => [
                    'value' => Money::fromDecimal($currency, $pricingConfig->chargeback_fee)->amount,
                    'currency' => strtoupper($currency),
                ],
                'balanceAccountId' => $balanceAccountId,
                'counterparty' => [
                    'balanceAccountId' => $account,
                ],
                'category' => 'internal',
            ]);
            $disputeFee->gateway_id = $data['reference'];
            $disputeFee->success = true;
        } catch (IntegrationApiException $e) {
            $disputeFee->success = false;
            $disputeFee->reason = $e->getMessage();

            if ($this->adyenLiveMode) {
                // Report the warning to Slack
                $company = $adyenAccount->tenant();
                $this->mailer->send([
                    'from_email' => 'no-reply@invoiced.com',
                    'to' => [['email' => 'b2b-payfac-notificati-aaaaqfagorxgbzwrnrb7unxgrq@flywire.slack.com', 'name' => 'Invoiced Payment Ops']],
                    'subject' => "Dispute Fee Collection Failed - {$company->name}",
                    'text' => "Collecting the dispute fee from an Adyen balance account has failed.
Tenant ID: {$company->id}
Account Holder: {$adyenAccount->account_holder_id}
Balance Account: $balanceAccountId
Fee Amount: $disputeFee->amount
PSP Reference: {$dispute->charge->gateway_id}
Dispute PSP Reference: {$dispute->gateway_id}
Error: {$e->getMessage()}",
                ]);
            }
        }

        $disputeFee->saveOrFail();
    }
}
