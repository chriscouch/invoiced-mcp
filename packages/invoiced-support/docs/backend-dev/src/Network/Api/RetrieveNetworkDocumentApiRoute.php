<?php

namespace App\Network\Api;

use App\AccountsPayable\Models\Vendor;
use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Network\Exception\DocumentStorageException;
use App\Network\Interfaces\DocumentStorageInterface;
use App\Network\Models\NetworkConnection;
use App\Network\Models\NetworkDocument;
use App\Network\Models\NetworkDocumentStatusTransition;
use App\Network\Traits\NetworkConnectionApiTrait;
use App\Network\Ubl\UblDocumentViewModelFactory;
use App\Network\Ubl\UblJsonTransformer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @extends AbstractModelApiRoute<NetworkDocument>
 */
class RetrieveNetworkDocumentApiRoute extends AbstractModelApiRoute
{
    use NetworkConnectionApiTrait;

    public function __construct(
        private TenantContext $tenant,
        private DocumentStorageInterface $documentStorage,
        private UblDocumentViewModelFactory $documentViewModelFactory,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: NetworkDocument::class,
            features: ['network'],
        );
    }

    public function buildResponse(ApiCallContext $context): array|Response
    {
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        /** @var NetworkDocument $document */
        $document = $this->retrieveModel($context);

        $tenant = $this->tenant->get();
        if ($document->from_company_id != $tenant->id && $document->to_company_id != $tenant->id) {
            throw new NotFoundHttpException();
        }

        $contentTypes = $context->request->getAcceptableContentTypes();
        if (in_array('application/pdf', $contentTypes)) {
            $xml = $this->getXml($document);
            $viewModel = $this->documentViewModelFactory->make($xml);
            // Return the first PDF found in the UBL document
            foreach ($viewModel->getAttachments() as $attachment) {
                if ('application/pdf' == $attachment['type']) {
                    return $this->downloadResponse($attachment['content'](), $attachment['type'], $attachment['name']);
                }
            }
        }

        if (in_array('text/xml', $contentTypes)) {
            $xml = $this->getXml($document);

            return $this->downloadResponse($xml, 'text/xml', 'document.xml');
        }

        $item = $document->toArray();

        // To/From Companies
        $item['from_company'] = $this->buildCompanyArray($document->from_company);
        $item['to_company'] = $this->buildCompanyArray($document->to_company);

        // Document Detail Reason
        if ($this->isParameterIncluded($context, 'detail')) {
            $xml = $this->getXml($document);

            $item['detail'] = (new UblJsonTransformer())->transform($xml);
        }

        // Current Status Reason
        if ($this->isParameterIncluded($context, 'current_status_reason')) {
            /** @var NetworkDocumentStatusTransition|null $transition */
            $transition = NetworkDocumentStatusTransition::where('document_id', $document)
                ->where('status', $document->current_status->value)
                ->sort('effective_date DESC,id DESC')
                ->oneOrNull();
            $item['current_status_reason'] = $transition?->description;
        }

        // Customer
        if ($this->isParameterIncluded($context, 'customer')) {
            $item['customer'] = null;
            if ($document->to_company_id != $tenant->id) {
                $networkConnection = NetworkConnection::where('customer_id', $document->to_company_id)
                    ->where('vendor_id', $tenant)
                    ->oneOrNull();
                if ($networkConnection) {
                    $item['customer'] = Customer::where('network_connection_id', $networkConnection)
                        ->oneOrNull()
                        ?->toArray();
                }
            }
        }

        // Vendor
        if ($this->isParameterIncluded($context, 'vendor')) {
            $item['vendor'] = null;
            if ($document->from_company_id != $tenant->id) {
                $networkConnection = NetworkConnection::where('vendor_id', $document->from_company)
                    ->where('customer_id', $tenant)
                    ->oneOrNull();
                if ($networkConnection) {
                    $item['vendor'] = Vendor::where('network_connection_id', $networkConnection)
                        ->oneOrNull()
                        ?->toArray();
                }
            }
        }

        return $item;
    }

    private function getXml(NetworkDocument $document): string
    {
        try {
            return $this->documentStorage->retrieve($document);
        } catch (DocumentStorageException $e) {
            throw new ApiError($e->getMessage());
        }
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
