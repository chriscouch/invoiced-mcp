<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Enums\PayableDocumentSource;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Operations\CreateBill;
use App\Core\Files\Models\BillAttachment;
use App\Core\Orm\Exception\DriverException;
use App\Core\Orm\Exception\ModelException;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Integrations\Textract\Models\TextractImport;
use Carbon\CarbonImmutable;

/**
 * @extends AbstractModelApiRoute<Bill>
 */
class CreateBillRoute extends AbstractModelApiRoute
{
    public function __construct(private readonly CreateBill $operation)
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
                'due_date' => new RequestParameter(types: ['string', 'null']),
                'currency' => new RequestParameter(types: ['string']),
                'line_items' => new RequestParameter(types: ['array']),
                'approval_workflow' => new RequestParameter(types: ['int', 'null']),
                'import_id' => new RequestParameter(types: ['int', 'null']),
            ],
            requiredPermissions: ['bills.edit'],
            modelClass: Bill::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): Bill
    {
        $parameters = $this->hydrateRelationships($context->requestParameters);
        $parameters['source'] = PayableDocumentSource::Keyed;

        if ($parameters['import_id']) {
            $parameters['source'] = PayableDocumentSource::InvoiceCapture;
        }

        if ($parameters['date']) {
            $parameters['date'] = new CarbonImmutable($parameters['date']);
        }

        if ($parameters['due_date']) {
            $parameters['due_date'] = new CarbonImmutable($parameters['due_date']);
        }

        try {
            $bill = $this->operation->create($parameters);
        } catch (DriverException $e) {
            if (strpos($e->getMessage(), 'Integrity constraint violation')) {
                throw new InvalidRequest('The given bill number has already been taken: '.$parameters['number'], 400, 'number');
            }

            throw new InvalidRequest($e->getMessage());
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        if ($parameters['import_id'] && $import = TextractImport::find($parameters['import_id'])) {
            $attachment = new BillAttachment();
            $attachment->file = $import->file;
            $attachment->bill = $bill;
            $attachment->save();

            $import->checkNeedsTraining($parameters);
            $import->bill = $bill;
            $import->save();
        }

        return $bill;
    }
}
