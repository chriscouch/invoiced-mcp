<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Libs\CheckPdf;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\ValueObjects\CheckPdfVariables;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Pdf\PdfStreamer;
use App\PaymentProcessing\Models\PaymentMethod;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractModelApiRoute<VendorPayment>
 */
class PrintVendorPaymentCheckApiRoute extends AbstractModelApiRoute
{
    public function __construct(
        private readonly CheckPdf $checkPdf,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: [],
            modelClass: VendorPayment::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $payment = $this->getModelOrFail(VendorPayment::class, $context->request->attributes->get('model_id'));

        if ($payment->voided) {
            throw new InvalidRequest('The payment has been voided.');
        }

        if (PaymentMethod::CHECK != $payment->payment_method) {
            throw new InvalidRequest('Payment method is not check.');
        }

        $checkLayout = $payment->bank_account?->check_layout;
        if (!$checkLayout) {
            throw new InvalidRequest('Check layout has not been set on the bank account.');
        }

        $checkVars = $this->buildCheckVars($payment);

        $filename = 'Print Check '.$payment->number.'.pdf';
        $this->checkPdf->setParameters([$checkVars], $checkLayout, $filename);
        $streamer = new PdfStreamer();

        return $streamer->stream($this->checkPdf, $payment->tenant()->getLocale());
    }

    private function buildCheckVars(VendorPayment $payment): array
    {
        $company = $payment->tenant();

        $amount = Money::fromDecimal($payment->currency, $payment->amount);
        $vendor = $payment->vendor;
        $checkVars = [
            'date' => $payment->date->format($company->date_format),
            'amount' => $amount,
            'currency' => $amount->currency,
            'vendor_name' => $vendor->name,
            'vendor_id' => $vendor->id,
            'bills' => [],
        ];

        foreach ($payment->getItems() as $paymentItem) {
            if ($bill = $paymentItem->bill) {
                $checkVars['bills'][] = [
                    'bill_id' => $bill->id,
                    'number' => $bill->number,
                    'amount' => $paymentItem->amount,
                    'date' => $bill->date->format($company->date_format),
                ];
            }
        }

        $pdfVariables = new CheckPdfVariables($amount);

        return array_merge($checkVars, $pdfVariables->jsonSerialize(), $vendor->getVendorAddress());
    }
}
