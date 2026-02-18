<?php

namespace App\Notifications\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\ACLModelRequester;
use App\Notifications\Models\NotificationEvent;
use Doctrine\DBAL\Connection;

class GetLatestUserNotificationsRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            requiresMember: true,
            modelClass: NotificationEvent::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        /** @var Member $member */
        $member = ACLModelRequester::get();

        $id = $this->connection->createQueryBuilder()
            ->select('notification_event_id')
            ->from('NotificationRecipients')
            ->where('member_id = :mid')
            ->setParameter('mid', $member->id)
            ->orderBy('notification_event_id', 'DESC')
            ->setMaxResults(1)
            ->fetchFirstColumn();

        $this->setModelId($id[0] ?? null);

        return parent::buildResponse($context);
    }
}
