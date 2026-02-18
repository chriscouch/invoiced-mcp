<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Exception\EstimateApprovalFormException;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Operations\ApproveEstimate;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Libs\PaymentRouter;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Operations\ProcessPayment;
use App\PaymentProcessing\Operations\VaultPaymentInfo;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\EstimateChargeApplicationItem;

class EstimateApprovalFormProcessor
{
    private const PAYMENT_MODE_AUTOPAY = 'autopay';
    private const PAYMENT_MODE_DEPOSIT = 'deposit';

    public function __construct(
        private ApproveEstimate $approveEstimate,
        private VaultPaymentInfo $vaultPaymentInfo,
        private ProcessPayment $processPayment,
    ) {
    }

    /**
     * Handles a form submission.
     *
     * @throws EstimateApprovalFormException when the submission fails for any reason
     */
    public function handleSubmit(Estimate $estimate, array $parameters, string $ip, string $userAgent): void
    {
        // determine the payment method
        $methodId = array_value($parameters, 'payment_method');
        $method = null;
        if ($methodId) {
            $method = PaymentMethod::instance($estimate->tenant(), $methodId);
        }

        // capture payment information if the customer uses AutoPay
        // DEPRECATED in estimate v2 approval workflow
        $paymentMode = array_value($parameters, 'payment_mode');
        $isAutoPay = self::PAYMENT_MODE_AUTOPAY == $paymentMode;
        if ($isAutoPay) {
            $sourceParams = (array) array_value($parameters, 'payment_source');
            $this->capturePaymentInformation($estimate, $method, $sourceParams);
        }

        // collect the deposit payment first
        // DEPRECATED in estimate v2 approval workflow
        if (self::PAYMENT_MODE_DEPOSIT == $paymentMode) {
            $sourceParams = array_value($parameters, 'payment_source');
            $this->collectDeposit($estimate, $method, $sourceParams);
        }

        // then mark the estimate as approved
        $initials = array_value($parameters, 'initials');
        if (!$initials || !$this->approveEstimate->approve($estimate, $ip, $userAgent, $initials, $isAutoPay)) {
            throw new EstimateApprovalFormException('Could not mark estimate as approved');
        }
    }

    /**
     * Captures and stores payment information.
     *
     * @deprecated
     *
     * @throws EstimateApprovalFormException when the payment information cannot be stored
     */
    private function capturePaymentInformation(Estimate $estimate, ?PaymentMethod $method, array $parameters): PaymentSource
    {
        if (!$method) {
            throw new EstimateApprovalFormException('Missing payment method!');
        }

        try {
            $source = $this->vaultPaymentInfo->save($method, $estimate->customer(), $parameters);
        } catch (PaymentSourceException $e) {
            throw new EstimateApprovalFormException($e->getMessage());
        }

        // When payment information is captured and there is a deposit,
        // we mark the deposit as paid because we do not support. This is
        // required by AJ Tutoring to not treat successful AutoPay
        // enrollments as unpaid estimates.
        if ($estimate->deposit > 0) {
            $estimate->deposit_paid = true;
            $estimate->save();
        }

        return $source;
    }

    /**
     * Collects a deposit payment.
     *
     * @deprecated
     *
     * @throws EstimateApprovalFormException when the deposit cannot be collected
     */
    private function collectDeposit(Estimate $estimate, ?PaymentMethod $method, array $parameters): void
    {
        if (!$method) {
            throw new EstimateApprovalFormException('Missing payment method!');
        }

        // Detect offline payments since there is no gateway involved
        $router = new PaymentRouter();
        $gateway = $router->getGateway($method, $estimate->customer(), [$estimate]);
        if (!$gateway) {
            return;
        }

        $items = [new EstimateChargeApplicationItem($estimate->getDepositBalance(), $estimate)];
        $chargeApplication = new ChargeApplication($items, PaymentFlowSource::CustomerPortal);

        try {
            $this->processPayment->pay($method, $estimate->customer(), $chargeApplication, $parameters, null);
        } catch (ChargeException $e) {
            // rethrow as a form exception
            throw new EstimateApprovalFormException($e->getMessage(), 0, $e);
        }
    }
}
