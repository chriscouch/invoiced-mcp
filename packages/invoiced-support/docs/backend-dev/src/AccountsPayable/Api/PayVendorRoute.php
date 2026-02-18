<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Exception\AccountsPayablePaymentException;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\CompanyBankAccount;
use App\AccountsPayable\Models\CompanyCard;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Operations\PayVendor;
use App\AccountsPayable\ValueObjects\PayVendorPayment;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\I18n\ValueObjects\Money;

/**
 * @extends AbstractModelApiRoute<VendorPayment>
 */
class PayVendorRoute extends AbstractModelApiRoute
{
    public function __construct(private PayVendor $payVendor)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'vendor' => new RequestParameter(
                    required: true,
                    types: ['int'],
                ),
                'currency' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'amount' => new RequestParameter(
                    required: true,
                    types: ['float', 'int'],
                ),
                'bank_account' => new RequestParameter(
                    types: ['int', 'null'],
                ),
                'card' => new RequestParameter(
                    types: ['int', 'null'],
                ),
                'payment_method' => new RequestParameter(
                    required: true,
                    types: ['string'],
                    allowedValues: ['credit_card', 'echeck', 'print_check'],
                ),
                'bills' => new RequestParameter(
                    required: true,
                    types: ['array'],
                ),
                'check_number' => new RequestParameter(
                    types: ['integer', 'null'],
                ),
            ],
            requiredPermissions: ['vendor_payments.create'],
            modelClass: VendorPayment::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): VendorPayment
    {
        $vendor = $this->getModelOrFail(Vendor::class, $context->requestParameters['vendor']);

        $currency = $context->requestParameters['currency'] ?? $vendor->tenant()->currency;
        $amount = Money::fromDecimal($currency, $context->requestParameters['amount']);

        $item = new PayVendorPayment($vendor);
        $appliedAmount = Money::zero($currency);
        foreach ($context->requestParameters['bills'] as $row) {
            $bill = $this->getModelOrFail(Bill::class, $row['bill'] ?? 0);
            $lineAmount = Money::fromDecimal($currency, $row['amount'] ?? 0);
            $appliedAmount = $appliedAmount->add($lineAmount);
            $item->addBill($bill, $lineAmount);
        }

        if (!$appliedAmount->equals($amount)) {
            throw new InvalidRequest('Applied amount ('.$appliedAmount.') does not equal payment amount ('.$amount.')');
        }

        $bankAccount = null;
        if (isset($context->requestParameters['bank_account'])) {
            $bankAccount = $this->getModelOrFail(CompanyBankAccount::class, $context->requestParameters['bank_account']);
        }

        $card = null;
        if (isset($context->requestParameters['card'])) {
            $card = $this->getModelOrFail(CompanyCard::class, $context->requestParameters['card']);
        }

        $options = [
            'bank_account' => $bankAccount,
            'card' => $card,
            'check_number' => $context->requestParameters['check_number'] ?? null,
        ];
        $paymentMethod = $context->requestParameters['payment_method'];

        try {
            return $this->payVendor->pay($paymentMethod, $item, $options);
        } catch (AccountsPayablePaymentException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
