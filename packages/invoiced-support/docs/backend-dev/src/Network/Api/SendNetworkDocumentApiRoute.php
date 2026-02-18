<?php

namespace App\Network\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\ACLModelRequester;
use App\Network\Command\SendDocument;
use App\Network\Exception\NetworkSendException;
use App\Network\Models\NetworkDocument;
use App\Network\Models\NetworkQueuedSend;
use App\Sending\Email\Interfaces\SendableDocumentInterface;

/**
 * @template T
 *
 * @extends AbstractRetrieveModelApiRoute<T>
 */
class SendNetworkDocumentApiRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private TenantContext $tenant,
        private SendDocument $sendDocument,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            features: ['network'],
        );
    }

    public function getSendModel(): mixed
    {
        return $this->model;
    }

    public function buildResponse(ApiCallContext $context): NetworkDocument|NetworkQueuedSend
    {
        parent::buildResponse($context);

        /** @var SendableDocumentInterface $model */
        $model = $this->getSendModel();

        $user = ACLModelRequester::get();
        if (!$user instanceof Member) {
            $user = null;
        }
        $connection = $model->getNetworkConnection();

        try {
            if (!$connection) {
                $customer = $model->getSendCustomer();

                return $this->sendDocument->queueToSend($user, $customer, $model);
            }

            $from = $this->tenant->get();
            if ($connection->vendor_id != $from->id) {
                throw new InvalidRequest('Only vendors can send documents');
            }

            return $this->sendDocument->sendFromModel($from, $user, $connection, $model);
        } catch (NetworkSendException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
