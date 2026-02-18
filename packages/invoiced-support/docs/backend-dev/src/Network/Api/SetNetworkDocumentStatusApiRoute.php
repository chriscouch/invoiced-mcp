<?php

namespace App\Network\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\ACLModelRequester;
use App\Network\Command\TransitionDocumentStatus;
use App\Network\Enums\DocumentStatus;
use App\Network\Models\NetworkDocument;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @extends AbstractRetrieveModelApiRoute<Response>
 */
class SetNetworkDocumentStatusApiRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private TenantContext $tenant,
        private TransitionDocumentStatus $transitionDocumentStatus,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [
                'status' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
                'description' => new RequestParameter(
                    types: ['string', 'null'],
                    default: null,
                ),
            ],
            requiredPermissions: [],
            modelClass: NetworkDocument::class,
            features: ['network'],
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        /** @var NetworkDocument $document */
        $document = parent::buildResponse($context);

        $tenant = $this->tenant->get();
        if ($document->from_company_id != $tenant->id && $document->to_company_id != $tenant->id) {
            throw new NotFoundHttpException();
        }

        $user = ACLModelRequester::get();
        if (!$user instanceof Member) {
            $user = null;
        }

        try {
            $toStatus = DocumentStatus::fromName($context->requestParameters['status']);
        } catch (RuntimeException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        // Check if transition is allowed
        $isSender = $document->from_company_id == $tenant->id;
        $fromStatus = $document->current_status;
        if (!$this->transitionDocumentStatus->isTransitionAllowed($fromStatus, $toStatus, $isSender)) {
            throw new InvalidRequest('You are not allowed to transition this document from '.$fromStatus->name.' to '.$toStatus->name);
        }

        // Perform the transition
        $this->transitionDocumentStatus->performTransition($document, $tenant, $toStatus, $user, $context->requestParameters['description'], true);

        return new Response('', 204);
    }
}
