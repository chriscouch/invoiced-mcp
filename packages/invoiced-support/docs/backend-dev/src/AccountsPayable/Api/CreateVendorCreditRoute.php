<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Enums\PayableDocumentSource;
use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Operations\CreateVendorCredit;
use App\Core\Files\Models\VendorCreditAttachment;
use App\Core\Orm\Exception\ModelException;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Integrations\Textract\Models\TextractImport;
use Carbon\CarbonImmutable;

/**
 * @extends AbstractModelApiRoute<VendorCredit>
 */
class CreateVendorCreditRoute extends AbstractModelApiRoute
{
    public function __construct(private readonly CreateVendorCredit $operation)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'vendor' => new RequestParameter(types: ['int']),
                'number' => new RequestParameter(types: ['string', 'null']),
                'date' => new RequestParameter(types: ['string']),
                'currency' => new RequestParameter(types: ['string']),
                'line_items' => new RequestParameter(types: ['array']),
                'approval_workflow' => new RequestParameter(types: ['int', 'null']),
                'import_id' => new RequestParameter(types: ['int', 'null']),
            ],
            requiredPermissions: ['bills.create'],
            modelClass: VendorCredit::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): VendorCredit
    {
        $parameters = $this->hydrateRelationships($context->requestParameters);
        $parameters['source'] = PayableDocumentSource::Keyed;

        if ($parameters['import_id']) {
            $parameters['source'] = PayableDocumentSource::InvoiceCapture;
        }

        if ($parameters['date']) {
            $parameters['date'] = new CarbonImmutable($parameters['date']);
        }

        try {
            $vendorCredit = $this->operation->create($parameters);
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        if ($parameters['import_id'] && $import = TextractImport::find($parameters['import_id'])) {
            $attachment = new VendorCreditAttachment();
            $attachment->file = $import->file;
            $attachment->vendor_credit = $vendorCredit;
            $attachment->save();

            $import->checkNeedsTraining($parameters);
            $import->vendor_credit = $vendorCredit;
            $import->save();
        }

        return $vendorCredit;
    }
}
