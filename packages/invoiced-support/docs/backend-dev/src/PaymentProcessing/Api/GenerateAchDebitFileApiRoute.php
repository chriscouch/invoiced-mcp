<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Utils\ModelUtility;
use App\PaymentProcessing\Ach\AchFileGenerator;
use App\PaymentProcessing\Enums\CustomerBatchPaymentStatus;
use App\PaymentProcessing\Models\AchFileFormat;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\CustomerPaymentBatch;
use App\PaymentProcessing\Models\CustomerPaymentBatchItem;
use App\PaymentProcessing\Models\PaymentMethod;
use Carbon\CarbonImmutable;
use Nacha\Exception;
use Nacha\Record\CcdEntry;
use Nacha\Record\Entry;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractModelApiRoute<CustomerPaymentBatch>
 */
class GenerateAchDebitFileApiRoute extends AbstractModelApiRoute
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
            modelClass: CustomerPaymentBatch::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $paymentBatch = $this->getModelOrFail(CustomerPaymentBatch::class, $context->request->attributes->get('model_id'));

        if (CustomerBatchPaymentStatus::Finished != $paymentBatch->status) {
            throw new InvalidRequest('The batch has not been finalized yet.');
        }

        if (PaymentMethod::ACH != $paymentBatch->payment_method) {
            throw new InvalidRequest('The payment method must be ACH.');
        }

        $format = $paymentBatch->ach_file_format;
        if (!$format) {
            throw new InvalidRequest('The batch does not have an ACH file format');
        }

        $effectiveDate = new CarbonImmutable($context->requestParameters['effective_date']);

        try {
            $entries = $this->buildEntries($paymentBatch);
            if (!$entries) {
                throw new InvalidRequest('This payment batch does not have any entries.');
            }

            $paymentFile = $this->achFileGenerator->makeDebits($format, $effectiveDate, $entries);
        } catch (Exception $e) {
            throw new InvalidRequest($e->getMessage());
        }

        $filename = 'ACH-'.$paymentBatch->number.'.txt';

        return $this->downloadResponse($paymentFile, 'text/plain', $filename);
    }

    /**
     * @return Entry[]
     */
    private function buildEntries(CustomerPaymentBatch $paymentBatch): array
    {
        /** @var AchFileFormat $format */
        $format = $paymentBatch->ach_file_format;

        // Load all charges belonging to the batch
        $query = CustomerPaymentBatchItem::where('customer_payment_batch_id', $paymentBatch)
            ->with('charge');
        $batchItems = ModelUtility::getAllModelsGenerator($query);

        $entries = [];
        /** @var CustomerPaymentBatchItem $batchItem */
        foreach ($batchItems as $batchItem) {
            $charge = $batchItem->charge;
            /** @var BankAccount $bankAccount */
            $bankAccount = $charge->payment_source;

            // Only uppercase A-Z and numbers are allowed
            $companyId = strtoupper((string) $charge->customer?->number);
            $companyId = preg_replace('/[^A-Z0-9 ]/', '', $companyId);

            $entry = new CcdEntry();
            $entry->setTransactionCode('savings' == $bankAccount->type ? '37' : '27') // 27 = Debit Checking, 37 = Debit Savings, 22 = Credit Checking, 32 = Credit Savings
                ->setReceivingDFiId(substr((string) $bankAccount->routing_number, 0, 8)) // First 8 digits of routing number
                ->setCheckDigit(substr((string) $bankAccount->routing_number, 8, 1)) // Last digit of routing number
                ->setReceivingDFiAccountNumber((string) $bankAccount->account_number) // Receiving account number
                ->setAmount($charge->amount)
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
