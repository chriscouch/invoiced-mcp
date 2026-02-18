<?php

namespace App\PaymentProcessing\Api\MerchantAccountTransactions;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\Adyen\Enums\ReportType;
use App\Integrations\Adyen\Models\AdyenReport;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use App\PaymentProcessing\Models\Payout;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractListModelsApiRoute<MerchantAccountTransaction>
 */
class ListMerchantAccountTransactionsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: MerchantAccountTransaction::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        //we check if payout was reconciled
        //if we have broken reports after payout creation
        //we return empty result set
        if ($id = $context->queryParameters['filter']['payout_id'] ?? null) {
            $payout = Payout::findOrFail($id);
            //report responsible for 11 Aug is usually created 12 Aug
            $created = CarbonImmutable::createFromTimestamp($payout->created_at)->addDay()->endOfDay();
            if (AdyenReport::where('report_type', [ReportType::BalancePlatformAccounting->value, ReportType::BalancePlatformPayout->value])
                ->where('processed', 0)
                ->where('created_at', $created, '<=')
                ->count()) {

                $this->response = new Response();
                $this->paginate($context, $this->response, 0, 25, null, []);
                return [];
            }
        }

        return parent::buildResponse($context);
    }
}
