<?php

namespace App\PaymentProcessing\Reconciliation;

use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;

/**
 * This reconciles payment information with our local database.
 */
class PaymentSourceReconciler implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    private static array $models = [
        CardValueObject::class => Card::class,
        BankAccountValueObject::class => BankAccount::class,
    ];

    /**
     * Reconciles a payment source from a payment gateway by creating
     * a payment source model.
     *
     * @throws ReconciliationException if unable to save the payment source
     */
    public function reconcile(SourceValueObject $source): PaymentSource
    {
        $model = $this->getModel($source);
        $paymentSource = null;

        // only check source if gateway_id is present.  One time charges do not have gateway_id, so we want to create a new source each time.
        if ($source->gatewayId) {
            // check if source already exists
            $paymentSource = $model::where('customer_id', $source->customer->id())
                ->where('gateway', $source->gateway)
                ->where('gateway_id', $source->gatewayId)
                ->oneOrNull();
        }

        if (!$paymentSource) {
            /** @var PaymentSource $paymentSource */
            $paymentSource = new $model();
            $paymentSource->customer = $source->customer;
            $paymentSource->gateway = $source->gateway;
            $paymentSource->gateway_id = $source->gatewayId;
            $paymentSource->gateway_setup_intent = $source->gatewaySetupIntent;
            $paymentSource->merchant_account_id = $source->merchantAccount?->id;
        }

        // update the source
        $paymentSource->gateway_customer = $source->gatewayCustomer;
        $paymentSource->chargeable = $source->chargeable;
        $paymentSource->receipt_email = $source->receiptEmail;

        if ($source instanceof CardValueObject && $paymentSource instanceof Card) {
            $this->reconcileCard($source, $paymentSource);
        } elseif ($source instanceof BankAccountValueObject && $paymentSource instanceof BankAccount) {
            $this->reconcileBankAccount($source, $paymentSource);
        }

        if (!$paymentSource->save()) {
            $this->statsd->increment('reconciliation.source.failed');

            throw new ReconciliationException('Unable to reconcile payment source: '.$paymentSource->getErrors());
        }

        $this->statsd->increment('reconciliation.source.succeeded');

        return $paymentSource;
    }

    /**
     * Gets the Invoiced payment source model for a source value object.
     *
     * @return class-string<PaymentSource>
     */
    private function getModel(SourceValueObject $source): string
    {
        $k = $source::class;

        return self::$models[$k];
    }

    /**
     * Reconciles a card source with its model.
     */
    private function reconcileCard(CardValueObject $source, Card $card): void
    {
        $card->brand = $source->brand;
        $card->funding = $source->funding;
        $card->last4 = $source->last4;
        $card->exp_month = $source->expMonth;
        $card->exp_year = $source->expYear;
        $card->issuing_country = $source->country;
    }

    /**
     * Reconciles a bank account source with its model.
     */
    private function reconcileBankAccount(BankAccountValueObject $source, BankAccount $account): void
    {
        $account->bank_name = $source->bankName;
        if (!$source->bankName) {
            $account->bank_name = 'UNKNOWN';
        }
        $account->account_number = $source->accountNumber;
        $account->routing_number = $source->routingNumber;
        $account->currency = $source->currency;
        $account->last4 = $source->last4;
        $account->country = $source->country;
        $account->account_holder_name = $source->accountHolderName;
        $account->account_holder_type = $source->accountHolderType;
        $account->type = $source->type;

        // Only store the verified status if the bank account is unsaved or has not been verified yet
        if (!$account->persisted() || !$account->verified) {
            $account->verified = $source->verified;
        }
    }
}
