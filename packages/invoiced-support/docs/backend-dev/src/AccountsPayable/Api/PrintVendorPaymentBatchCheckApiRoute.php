<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Enums\VendorBatchPaymentStatus;
use App\AccountsPayable\Libs\CheckPdf;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Models\VendorPaymentBatch;
use App\AccountsPayable\ValueObjects\CheckPdfVariables;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\TenantContext;
use App\Core\Pdf\PdfStreamer;
use App\Core\Utils\ModelUtility;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractModelApiRoute<VendorPaymentBatch>
 */
class PrintVendorPaymentBatchCheckApiRoute extends AbstractModelApiRoute
{
    public function __construct(
        private readonly CheckPdf $checkPdf,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: [],
            modelClass: VendorPaymentBatch::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $paymentBatch = $this->getModelOrFail(VendorPaymentBatch::class, $context->request->attributes->get('model_id'));

        if (VendorBatchPaymentStatus::Finished != $paymentBatch->status) {
            throw new InvalidRequest('The batch has not been processed yet.');
        }

        if ('print_check' != $paymentBatch->payment_method) {
            throw new InvalidRequest('The payment method must be print check.');
        }

        if (!$paymentBatch->check_layout) {
            throw new InvalidRequest('Check layout has not been set on the batch.');
        }

        $checks = $this->buildCheckVars($paymentBatch);
        if (!$checks) {
            throw new InvalidRequest('There are no selected bills to create checks', 400);
        }

        $filename = 'Print Check '.$paymentBatch->number.'.pdf';
        $this->checkPdf->setParameters($checks, $paymentBatch->check_layout, $filename);
        $streamer = new PdfStreamer();

        return $streamer->stream($this->checkPdf, $this->tenantContext->get()->getLocale());
    }

    private function buildCheckVars(VendorPaymentBatch $paymentBatch): array
    {
        $company = $this->tenantContext->get();

        // Load all vendor payments created by the batch
        $query = VendorPayment::where('vendor_payment_batch_id', $paymentBatch)
            ->where('payment_method', 'check')
            ->with('vendor');
        $vendorPayments = ModelUtility::getAllModelsGenerator($query);

        $checks = [];
        foreach ($vendorPayments as $vendorPayment) {
            $amount = Money::fromDecimal($vendorPayment->currency, $vendorPayment->amount);
            $vendor = $vendorPayment->vendor;
            $checkVars = [
                'date' => $vendorPayment->date->format($company->date_format),
                'amount' => $amount,
                'currency' => $amount->currency,
                'vendor_name' => $vendor->name,
                'vendor_id' => $vendor->id,
                'bills' => [],
            ];

            foreach ($vendorPayment->getItems() as $paymentItem) {
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
            $checkVars = array_merge($checkVars, $pdfVariables->jsonSerialize(), $vendor->getVendorAddress());
            $checks[] = $checkVars;
        }

        return $checks;
    }
}
