<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\AccountsPayable\Enums\VendorBatchPaymentStatus;
use App\AccountsPayable\Ledger\BillBalanceGenerator;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\PaymentMethods\VendorPaymentMethods;
use App\Core\RestApi\Normalizers\ModelApiNormalizer;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Core\Utils\ModelUtility;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ListBatchBillsRoute extends AbstractApiRoute
{
    public function __construct(
        private readonly ModelApiNormalizer $normalizer,
        private readonly BillBalanceGenerator $balanceGenerator,
        private readonly TenantContext $tenantContext,
        private readonly Connection $database,
        private readonly VendorPaymentMethods $paymentMethods,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Bill::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $ids = $this->database->createQueryBuilder()
            ->select('bill_id')
            ->from('VendorPaymentBatchBills', 'b')
            ->join('b', 'VendorPaymentBatches', 'a', 'b.vendor_payment_batch_id = a.id')
            ->andWhere('b.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->tenantContext->get()->id())
            ->andWhere('a.status NOT IN ('.VendorBatchPaymentStatus::Finished->value.','.VendorBatchPaymentStatus::Voided->value.')')
            ->fetchFirstColumn();

        $qry = Bill::where('status', PayableDocumentStatus::Approved->value);
        if ($ids) {
            $ids = implode(',', $ids);
            $qry->where("id NOT IN($ids)");
        }

        $bills = ModelUtility::getAllModelsGenerator($qry);

        $vendorMap = [];
        $result = [];
        /** @var Bill $bill */
        foreach ($bills as $bill) {
            $balance = $this->balanceGenerator->getBalance($bill);
            if (!$balance->isPositive()) {
                continue;
            }

            $vendorId = $bill->vendor_id;
            if (!isset($vendorMap[$vendorId])) {
                $vendor = $bill->vendor;
                $result[] = [
                    'vendor' => $this->normalizer->normalize($vendor),
                    'payment_methods' => $this->paymentMethods->getForVendor($vendor),
                    'bills' => [],
                ];
                $vendorMap[$vendorId] = count($result) - 1;
            }

            $billArray = $this->normalizer->normalize($bill);
            $billArray['balance'] = $balance->toDecimal();
            $billArray['date'] = $bill->date->format('Y-m-d');
            $billArray['due_date'] = $bill->due_date?->format('Y-m-d');
            $result[$vendorMap[$vendorId]]['bills'][] = $billArray;
        }

        return new JsonResponse($result);
    }
}
