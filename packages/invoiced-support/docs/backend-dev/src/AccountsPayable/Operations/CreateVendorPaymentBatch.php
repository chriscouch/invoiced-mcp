<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Enums\CheckStock;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\CompanyBankAccount;
use App\AccountsPayable\Models\CompanyCard;
use App\AccountsPayable\Models\VendorPaymentBatch;
use App\AccountsPayable\Models\VendorPaymentBatchBill;
use App\Companies\Models\Member;
use App\Core\Authentication\Libs\UserContext;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Exception\ModelException;

class CreateVendorPaymentBatch
{
    public function __construct(
        private readonly UserContext $userContext,
        private TenantContext $tenant,
    ) {
    }

    /**
     * @throws ModelException
     */
    public function create(array $parameters): VendorPaymentBatch
    {
        if (isset($parameters['bank_account'])) {
            $parameters['bank_account'] = CompanyBankAccount::findOrFail($parameters['bank_account']);
        }

        if (isset($parameters['card'])) {
            $parameters['card'] = CompanyCard::findOrFail($parameters['card']);
        }

        if (!isset($parameters['currency'])) {
            $parameters['currency'] = $this->tenant->get()->currency;
        }

        if (isset($parameters['check_layout'])) {
            foreach (CheckStock::cases() as $checkLayout) {
                if ($checkLayout->name == $parameters['check_layout']) {
                    $parameters['check_layout'] = $checkLayout;
                }
            }
        }

        $batchPayment = new VendorPaymentBatch();
        foreach ($parameters as $k => $v) {
            $batchPayment->$k = $v;
        }

        $batchPayment->member = Member::getForUser($this->userContext->getOrFail());
        $batchPayment->saveOrFail();

        $total = Money::zero($batchPayment->currency);
        foreach ($parameters['bills'] as $item) {
            $bill = Bill::findOrFail($item['id']);
            if (strtolower($bill->currency) != strtolower($batchPayment->currency)) {
                throw new ModelException('Bill currency ('.$bill->currency.') must match payment currency ('.$batchPayment->currency.')');
            }

            $amount = Money::fromDecimal($bill->currency, $item['amount']);
            $total = $total->add($amount);

            $batchPaymentItem = new VendorPaymentBatchBill();
            $batchPaymentItem->amount = $amount->toDecimal();
            $batchPaymentItem->bill = $bill;
            $batchPaymentItem->bill_number = $bill->number;
            $batchPaymentItem->vendor_payment_batch = $batchPayment;
            $batchPaymentItem->vendor_id = $bill->vendor_id;
            $batchPaymentItem->saveOrFail();
        }

        $batchPayment->total = $total->toDecimal();
        $batchPayment->saveOrFail();

        return $batchPayment;
    }
}
