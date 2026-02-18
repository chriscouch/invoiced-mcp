<?php

namespace App\PaymentProcessing\Libs;

use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Exception\ModelException;
use App\Core\Utils\RandomString;
use App\CustomerPortal\Exceptions\PaymentLinkException;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\Models\AdyenPaymentResult;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Enums\PaymentMethodType;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentFlowApplication;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentFlowManager
{
    public function __construct(
        private readonly AdyenClient           $adyen,
        private readonly PaymentFlowReconciliationOperation  $paymentFlowReconciliationOperation,
    ) {
    }

    /**
     * Creates a new payment flow.
     *
     * @param PaymentFlowApplication[] $applications
     *
     * @throws ModelException
     */
    public function create(PaymentFlow $flow, array $applications = []): void
    {
        $flow->identifier = RandomString::generate(32, RandomString::CHAR_LOWER.RandomString::CHAR_NUMERIC);
        $flow->status = PaymentFlowStatus::CollectPaymentDetails;
        $flow->saveOrFail();

        foreach ($applications as $application) {
            $application->payment_flow = $flow;
            $application->saveOrFail();
        }
    }

    /**
     * @throws FormException|PaymentLinkException
     */
    public function handleCompletePage(PaymentFlow $flow, Request $request): ?Response
    {
        if (PaymentFlowStatus::Succeeded == $flow->status) {
            return null;
        }

        // Check if further action is needed to complete
        // eg pass the payment details to Adyen.

        // Handle Adyen 3DS redirect response
        $needsReconciliation = false;
        $completeParameters = [];
        if ($request->query->has('redirectResult')) {
            $completeParameters = $this->completeAdyen($flow, $request);
            $needsReconciliation = true;
        }

        // Complete the payment action when needed
        if ($needsReconciliation) {
            return $this->paymentFlowReconciliationOperation->reconcile($flow, $completeParameters);
        }

        // Redirect to the return URL instead of our thanks page, if known
        if ($flow->return_url) {
            return new RedirectResponse($flow->return_url);
        }

        return null;
    }

    /**
     * @throws FormException
     */
    private function completeAdyen(PaymentFlow $flow, Request $request): array
    {
        try {
            $result = $this->adyen->submitPaymentDetails([
                'details' => [
                    'redirectResult' => $request->query->get('redirectResult'),
                ],
            ]);
        } catch (IntegrationApiException $e) {
            throw new FormException($e->getMessage());
        }

        if (!$flow->payment_method) {
            $flow->payment_method = PaymentMethodType::Card;
        }
        // If the payment is completed (no action required) then save the result for future reconciliation
        $this->saveResult($flow->identifier, $result, $flow);
        // we still update flow if no payment result present
        if (!isset($result['pspReference'])) {
            $flow->save();
        }

        return [
            'payment_method' => $flow->payment_method ? $flow->payment_method->toString() : 'card',
            'reference' => $flow->identifier,
        ];
    }

    /** get processing amount for receivable document, based  */
    private function getProcessingAmountForInvoice(ReceivableDocument $document): Money
    {
        $money = Money::zero($document->currency);

        switch (get_class($document)) {
            case Invoice::class:
                $key = 'invoice_id';
                break;
            case Estimate::class:
                $key = 'estimate_id';
                break;
            default:
                return $money;
        }

        //payment flows for estimates are not implemented yet, so it may cause false negatives
        $applications = PaymentFlowApplication::with('payment_flow')
            ->where($key, $document)
            ->all();
        foreach ($applications as $application) {
            $flow = $application->payment_flow;
            if (!$flow->completed_at && in_array($flow->status, [PaymentFlowStatus::ActionRequired, PaymentFlowStatus::Processing])) {
                $money = $money->add(Money::fromDecimal($document->currency, $application->amount));
            }
        }

        return $money;
    }

    public function getBlockingAmount(ReceivableDocument $document, Money $amountToPay): Money
    {
        $runningBalance = Money::zero($document->currency);
        switch (get_class($document)) {
            case Invoice::class:
                $runningBalance = Money::fromDecimal($document->currency, $document->balance);
                break;
            case Estimate::class:
                $runningBalance = Money::fromDecimal($document->currency, $document->deposit);
                break;
            default:
                return $runningBalance;
        }
        //subtract the current application
        $runningBalance = $runningBalance->subtract($amountToPay);
        $money = $this->getProcessingAmountForInvoice($document);
        return $runningBalance->lessThanOrEqual($money) ? $money : Money::zero($document->currency);
    }

    public function saveResult(string $reference, array $result, ?PaymentFlow $flow = null): void
    {
        if (!isset($result['pspReference'])) {
            return;
        }

        if ($flow) {
            $flow->status = match ($result['resultCode']) {
                'Authorised', 'Received' => PaymentFlowStatus::Processing,
                default => PaymentFlowStatus::Failed,
            };

            if (isset($result['additionalData'])) {
                $expiry = $result['additionalData']['expiryDate'] ?? '';
                $expiryParts = explode('/', $expiry);

                $flow->funding = strtolower($result['additionalData']['funding'] ?? 'unknown');
                $flow->last4 = $result['additionalData']['cardSummary'] ?? null;
                $flow->expMonth = (int) ($expiryParts[0] ?? 12);
                $flow->expYear = (int) ($expiryParts[1] ?? date('Y'));
                $flow->country = $result['additionalData']['cardIssuingCountry'] ?? null;
            }

            $flow->save();
        }

        $model = new AdyenPaymentResult();
        $model->reference = $reference;
        $model->result = (string) json_encode($result);
        $model->saveOrFail();
    }
}
