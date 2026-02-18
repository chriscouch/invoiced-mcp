<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Models\CompanyBankAccount;
use App\AccountsPayable\Models\ECheck;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Models\VendorPaymentBatch;
use App\AccountsPayable\ValueObjects\PayVendorPayment;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\Core\Utils\InfuseUtility as Utility;
use App\EntryPoint\QueueJob\SendECheckQueueJob;
use App\PaymentProcessing\Enums\PaymentMethodType;
use Carbon\CarbonImmutable;
use App\Core\Orm\Exception\ModelException;

class CreateECheck
{
    public function __construct(
        private readonly Queue $queue,
        private readonly CreateVendorPayment $createVendorPayment,
    ) {
    }

    /**
     * @throws ModelException
     */
    public function create(PayVendorPayment $payment, array $parameters, CompanyBankAccount $account, Vendor $vendor, ?VendorPaymentBatch $paymentBatch = null): VendorPayment
    {
        $email = $parameters['email'];
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ModelException('Email is invalid');
        }

        $company = $account->tenant();
        if ('US' !== $company->country) {
            throw new ModelException('The feature available only in the USA');
        }

        // Create the VendorPayment model
        $currency = $payment->getAmount()->currency;
        $paymentParameters = [
            'vendor' => $vendor,
            'date' => CarbonImmutable::now(),
            'amount' => $parameters['amount']->toDecimal(),
            'currency' => $currency,
            'payment_method' => PaymentMethodType::Echeck->toString(),
            'reference' => $parameters['check_number'],
            'bank_account' => $account,
        ];
        if ($paymentBatch) {
            $paymentParameters['vendor_payment_batch'] = $paymentBatch;
        }

        $total = Money::fromDecimal($currency, 0);
        $appliedTo = [];
        foreach ($payment->getItems() as $item) {
            $total = $total->add($item->amount);
            $appliedTo[] = [
                'bill' => $item->bill,
                'amount' => $item->amount->toDecimal(),
            ];
        }

        $vendorPayment = $this->createVendorPayment->create($paymentParameters, $appliedTo);

        // Create the ECheck model
        $id = strtolower(Utility::guid());
        $eCheck = new ECheck();
        $eCheck->hash = $id;
        $eCheck->payment = $vendorPayment;
        $eCheck->account = $account;
        $eCheck->address1 = $parameters['address1'];
        $eCheck->address2 = $parameters['address2'];
        $eCheck->city = $parameters['city'];
        $eCheck->state = $parameters['state'];
        $eCheck->postal_code = $parameters['postal_code'];
        $eCheck->country = $parameters['country'];
        $eCheck->email = $parameters['email'];
        $eCheck->amount = $parameters['amount']->toDecimal();
        $eCheck->check_number = $parameters['check_number'];
        $eCheck->saveOrFail();

        // Email the ECheck
        $this->queueToSend($eCheck);

        return $vendorPayment;
    }

    public function queueToSend(ECheck $eCheck): void
    {
        $this->queue->enqueue(SendECheckQueueJob::class, [
            'tenant_id' => $eCheck->tenant_id,
            'check_id' => $eCheck->id,
        ], QueueServiceLevel::Normal);
    }
}
