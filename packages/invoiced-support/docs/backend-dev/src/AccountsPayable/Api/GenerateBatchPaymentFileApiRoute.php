<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Enums\VendorBatchPaymentStatus;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Models\VendorPaymentBatch;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Utils\ModelUtility;
use App\PaymentProcessing\Ach\AchFileGenerator;
use App\PaymentProcessing\Models\AchFileFormat;
use App\PaymentProcessing\Models\PaymentMethod;
use Carbon\CarbonImmutable;
use Nacha\Exception;
use Nacha\Record\CcdEntry;
use Nacha\Record\Entry;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractModelApiRoute<VendorPaymentBatch>
 */
class GenerateBatchPaymentFileApiRoute extends AbstractModelApiRoute
{
    public function __construct(
        private AchFileGenerator $achFileGenerator,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'effective_date' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
            ],
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

        if (PaymentMethod::ACH != $paymentBatch->payment_method) {
            throw new InvalidRequest('The payment method must be ACH.');
        }

        $fromBankAccount = $paymentBatch->bank_account;
        if (!$fromBankAccount) {
            throw new InvalidRequest('The batch does not have a payment bank account.');
        }

        $format = $fromBankAccount->ach_file_format;
        if (!$format) {
            throw new InvalidRequest('The payment bank account does not have an ACH file format');
        }

        $effectiveDate = new CarbonImmutable($context->requestParameters['effective_date']);

        try {
            $entries = $this->buildEntries($paymentBatch, $format);
            if (!$entries) {
                throw new InvalidRequest('This payment batch does not have any entries.');
            }

            $paymentFile = $this->achFileGenerator->makeCredits($format, $effectiveDate, $entries);
        } catch (Exception $e) {
            throw new InvalidRequest($e->getMessage());
        }

        $filename = 'ACH-'.$paymentBatch->number.'.txt';

        return $this->downloadResponse($paymentFile, 'text/plain', $filename);
    }

    /**
     * @return Entry[]
     */
    private function buildEntries(VendorPaymentBatch $paymentBatch, AchFileFormat $format): array
    {
        // Load all vendor payments created by the batch
        $query = VendorPayment::where('vendor_payment_batch_id', $paymentBatch)
            ->where('payment_method', 'ach')
            ->with('vendor');
        $vendorPayments = ModelUtility::getAllModelsGenerator($query);

        $entries = [];
        /** @var VendorPayment $vendorPayment */
        foreach ($vendorPayments as $vendorPayment) {
            $vendor = $vendorPayment->vendor;
            $bankAccount = $vendor->bank_account;
            if (!$bankAccount) {
                throw new InvalidRequest($vendor->name.' does not have a bank account.');
            }

            // Only uppercase A-Z and numbers are allowed
            $companyId = strtoupper($vendor->number);
            $companyId = preg_replace('/[^A-Z0-9 ]/', '', $companyId);

            $entry = new CcdEntry();
            $entry->setTransactionCode('savings' == $bankAccount->type ? '32' : '22') // 22 = Credit Checking, 32 = Credit Savings
                ->setReceivingDFiId(substr((string) $bankAccount->routing_number, 0, 8)) // First 8 digits of routing number
                ->setCheckDigit(substr((string) $bankAccount->routing_number, 8, 1)) // Last digit of routing number
                ->setReceivingDFiAccountNumber($bankAccount->account_number) // Receiving account number
                ->setAmount($vendorPayment->amount)
                ->setReceivingCompanyId($companyId) // Identify the transaction to the Receiver.
                ->setReceivingCompanyName((string) $bankAccount->account_holder_name)
                ->setTraceNumber($format->originating_dfi_identification, 1);
            $entries[] = $entry;
        }

        return $entries;
    }

    private function downloadResponse(string $content, string $type, string $filename): Response
    {
        return new Response($content, 200, [
            'Content-Type' => $type,
            'Cache-Control' => 'public, must-revalidate, max-age=0',
            'Pragma' => 'public',
            'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Content-Length' => strlen($content),
            'Content-Disposition' => 'attachment; filename="'.$filename.'";',
            // allow to be embedded in an iframe
            'X-Frame-Options' => 'ALLOW',
        ]);
    }
}
