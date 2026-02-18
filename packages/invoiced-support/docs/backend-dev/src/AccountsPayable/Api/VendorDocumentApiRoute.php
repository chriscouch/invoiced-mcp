<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\AccountsPayable\Libs\VendorDocumentResolver;
use App\AccountsPayable\Models\PayableDocument;
use App\AccountsPayable\Operations\VendorDocumentEditOperation;
use App\Companies\Models\Member;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\Orm\ACLModelRequester;
use App\Network\Command\TransitionDocumentStatus;
use App\Network\Enums\DocumentStatus;
use Throwable;

/**
 * @extends AbstractModelApiRoute<PayableDocument>
 */
abstract class VendorDocumentApiRoute extends AbstractModelApiRoute
{
    public function __construct(
        private readonly VendorDocumentEditOperation $operation,
        private readonly TransitionDocumentStatus $transitionDocumentStatus,
        private readonly VendorDocumentResolver $resolver)
    {
    }

    public function buildResponse(ApiCallContext $context): PayableDocument
    {
        /** @var Member $member */
        $member = ACLModelRequester::get();

        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        /** @var PayableDocument $vendorDocument */
        $vendorDocument = $this->retrieveModel($context);

        $networkDocument = $vendorDocument->network_document;
        if ($networkDocument) {
            $fromStatus = $networkDocument->current_status;
            $toStatus = $this->getDocumentStatus();
            // we check if we are in recipient company scope
            if ($networkDocument->to_company_id != $vendorDocument->tenant_id) {
                throw new InvalidRequest('You are not allowed to transition this document from '.$fromStatus->name.' to '.$toStatus->name);
            }
        }

        $description = $context->requestParameters['description'];
        $this->resolver->resolve($vendorDocument, $member, $this->getPayableDocumentStatus(), $description);

        $vendorDocument->resolveTask($member);

        if (!$this->shouldPerformTransition($vendorDocument)) {
            return $vendorDocument;
        }

        $this->updateDocument($vendorDocument, [
            'status' => $this->getPayableDocumentStatus(),
            'approval_workflow_step' => null,
        ]);

        try {
            if ($networkDocument) {
                if (!$this->transitionDocumentStatus->isTransitionAllowed($fromStatus, $toStatus, false)) {
                    throw new InvalidRequest('You are not allowed to transition this document from '.$fromStatus->name.' to '.$toStatus->name);
                }

                // Perform the transition
                $this->transitionDocumentStatus->performTransition($networkDocument, $vendorDocument->tenant(), $toStatus, $member, $description, true);
            }
        } catch (Throwable) {
        }

        return $vendorDocument;
    }

    protected function shouldPerformTransition(PayableDocument $vendorDocument): bool
    {
        return true;
    }

    protected function updateDocument(PayableDocument $document, array $parameters): void
    {
        $this->operation->edit($document, $parameters);
    }

    abstract protected function getDocumentStatus(): DocumentStatus;

    abstract protected function getPayableDocumentStatus(): PayableDocumentStatus;
}
