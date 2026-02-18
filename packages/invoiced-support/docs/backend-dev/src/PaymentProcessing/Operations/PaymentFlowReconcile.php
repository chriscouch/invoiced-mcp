<?php

namespace App\PaymentProcessing\Operations;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Core\Database\TransactionManager;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Exception\ModelException;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\CustomerPortal\Command\PaymentLinkProcessor;
use App\CustomerPortal\Command\PaymentLinks\PaymentLinkCustomerHandler;
use App\CustomerPortal\Command\PaymentLinks\PaymentLinkInvoiceHandler;
use App\CustomerPortal\Exceptions\PaymentLinkException;
use App\CustomerPortal\ValueObjects\PaymentLinkResult;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Interfaces\ChargeApplicationItemInterface;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentFlowApplication;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\AppliedCreditChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\ConvenienceFeeChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\CreditChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\CreditNoteChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\EstimateChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\InvoiceChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\PaymentFlowReconcileData;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class PaymentFlowReconcile implements LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    public function __construct(
        private readonly PaymentSourceReconciler $sourceReconciler,
        private readonly ProcessPayment $processPayment,
        private readonly PaymentLinkCustomerHandler $customerHandler,
        private readonly PaymentLinkInvoiceHandler $invoiceHandler,
        private readonly TransactionManager $transaction,
        private readonly PaymentLinkProcessor $paymentLinkProcessor,
    ) {
    }

    /**
     * Reconcile Payment Flow.
     *
     * @throws FormException|PaymentLinkException|ChargeException
     */
    public function doReconcile(PaymentFlow $flow, PaymentFlowReconcileData $data, string $methodId = PaymentMethod::CREDIT_CARD): ?Payment
    {
        if (!$gateway = $flow->gateway) {
            $this->logger->error("Payment flow is missing gateway for reference: {$flow->identifier}");

            return null;
        }

        $paymentLinkResult = null;
        $paymentLinkParameters = [];

        $customer = $flow->customer;
        if (!$customer) {
            $this->logger->error("Customer not found for reference: {$flow->identifier}");

            return null;
        }

        $flowApplications = $this->getFlowApplications($flow, $data->amount, $paymentLinkResult, $paymentLinkParameters);
        if (!$flowApplications) {
            return null;
        }
        $paymentSplits = $this->getFlowSplits($flow, $flowApplications, $data->amount);

        if (!$paymentSplits) {
            return null;
        }

        $method = PaymentMethod::where('id', $methodId)
            ->where('gateway', $gateway)
            ->where('enabled', true)
            ->oneOrNull();
        if (!$method) {
            $method = new PaymentMethod();
            $method->id = $methodId;
        }
        $merchantAccount = $flow->merchant_account;
        if (!$merchantAccount) {
            $this->logger->error("Merchant Account missing for reference: {$flow->identifier}");

            return null;
        }

        $chargeApplication = new ChargeApplication($paymentSplits, $flow->initiated_from);
        $description = GatewayHelper::makeDescription($chargeApplication->getDocuments());
        $charge = $this->buildChargeReference($customer, $merchantAccount, $data->amount, $data, $method->id, $flow->email, $description);

        $payment = $this->processPayment->reconcileCharge($charge, $method, $chargeApplication, $flow->email, $flow);

        if ($paymentLinkResult) {
            $paymentLinkResult->setPayment($payment);
            $this->paymentLinkProcessor->createSession($paymentLinkResult, $paymentLinkParameters);
            $this->paymentLinkProcessor->afterTransaction($paymentLinkResult);
        }

        return $payment;
    }

    /**
     * @return PaymentFlowApplication[]
     */
    public function getFlowApplications(PaymentFlow $flow, Money $amount, ?PaymentLinkResult &$paymentLinkResult, array &$paymentLinkParameters): array
    {
        /** @var PaymentFlowApplication[] $flowApplications */
        $flowApplications = $flow->getApplications();
        if (!$flowApplications) {
            if ($flow->payment_link) {
                $this->transaction->perform(function () use ($flow, $amount, &$paymentLinkResult, &$paymentLinkParameters) {
                    $paymentLinkParameters = $this->paymentLinkProcessor->buildFormParametersFromFormSubmission($flow, []);

                    $paymentLinkResult = new PaymentLinkResult($flow->payment_link);
                    $paymentLinkResult->setPaymentFlow($flow);

                    try {
                        // Create or find the customer
                        $this->customerHandler->handle($paymentLinkResult, $paymentLinkParameters);
                        // Create the invoice
                        $this->invoiceHandler->handle($paymentLinkResult, $amount, $paymentLinkParameters);
                    } catch (ModelException $e) {
                        throw new PaymentLinkException($e->getMessage(), $e->getCode(), $e);
                    }
                });
            }

            $flowApplications = $flow->getApplications();
            if (!$flowApplications) {
                $this->logger->error("Payment flow is missing applications for reference: {$flow->identifier}");
            }
        }

        return $flowApplications;
    }

    /**
     * @param PaymentFlowApplication[] $flowApplications
     * @return ?ChargeApplicationItemInterface[]
     */
    public function getFlowSplits(PaymentFlow $flow, array $flowApplications, Money $amount): ?array
    {
        $paymentSplits = [];
        $runningBalance = Money::zero($flow->currency);

        foreach ($flowApplications as $application) {
            $type = $application->type->toString();
            $money = Money::fromDecimal($flow->currency, $application->amount);
            $document = $application->invoice ?? $application->estimate;

            if (PaymentItemType::Estimate->value == $type) {
                if (!$application->estimate) {
                    $this->logger->error("Estimate is missing document type for reference: {$flow->identifier}");

                    return null;
                }
                $runningBalance = $runningBalance->add($money);
                $paymentSplits[] = new EstimateChargeApplicationItem($money, $application->estimate);
            } elseif (PaymentItemType::Invoice->value == $type) {
                if (!$application->invoice) {
                    $this->logger->error("Invoice is missing document type for reference: {$flow->identifier}");

                    return null;
                }
                $runningBalance = $runningBalance->add($money);
                $paymentSplits[] = new InvoiceChargeApplicationItem($money, $application->invoice);
            } elseif (PaymentItemType::Credit->value == $type) {
                $runningBalance = $runningBalance->add($money);
                $paymentSplits[] = new CreditChargeApplicationItem($money);
            } elseif (PaymentItemType::CreditNote->value == $type) {
                if (!$document || !$application->credit_note) {
                    $this->logger->error("Credit note is missing document type for reference: {$flow->identifier}");

                    return null;
                }
                $paymentSplits[] = new CreditNoteChargeApplicationItem($money, $application->credit_note, $document);
            } elseif (PaymentItemType::AppliedCredit->value == $type) {
                if (!$document) {
                    $this->logger->error("Applied credit is missing document type for reference: {$flow->identifier}");

                    return null;
                }
                $paymentSplits[] = new AppliedCreditChargeApplicationItem($money, $document);
            } elseif (PaymentItemType::ConvenienceFee->value == $type) {
                $runningBalance = $runningBalance->add($money);
                $paymentSplits[] = new ConvenienceFeeChargeApplicationItem($money);
            }
        }

        if (!$runningBalance->equals($amount)) {
            $this->logger->error("Payment amount does not equal reference: {$flow->identifier} {$runningBalance->toDecimal()} {$amount->toDecimal()}");

            return null;
        }

        return $paymentSplits;
    }

    /**
     * @throws ChargeException
     */
    public function buildChargeReference(Customer $customer, MerchantAccount $account, Money $amount, PaymentFlowReconcileData $data, string $method, ?string $receiptEmail, string $description): ChargeValueObject
    {
        try {
            $sourcedValueObject = $data->toSourceValueObject($customer, $account, false, $receiptEmail, $method);
            $source = $this->sourceReconciler->reconcile($sourcedValueObject);
        } catch (ReconciliationException $e) {
            throw new ChargeException($e->getMessage());
        }

        return new ChargeValueObject(
            customer: $customer,
            amount: $amount,
            gateway: $account->gateway,
            gatewayId: $data->gatewayId,
            method: $method,
            status: $data->status,
            merchantAccount: $account,
            source: $source,
            description: $description,
            failureReason: $data->failureReason,
        );
    }

    public function reconcile(PaymentFlow $flow, PaymentFlowReconcileData $data, string $method = PaymentMethod::CREDIT_CARD): ?Payment
    {
        try {
            return $this->doReconcile($flow, $data, $method);
            // handle empty form submit data
        } catch (FormException|PaymentLinkException|ChargeException $e) {
            $this->logger->error($e->getMessage());
        }

        return null;
    }
}
