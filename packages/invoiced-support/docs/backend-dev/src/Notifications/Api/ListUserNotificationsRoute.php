<?php

namespace App\Notifications\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Query;
use App\Notifications\Libs\NotificationEventSerializer;
use App\Notifications\Models\NotificationRecipient;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

/**
 * @extends AbstractListModelsApiRoute<NotificationRecipient>
 */
class ListUserNotificationsRoute extends AbstractListModelsApiRoute
{
    private Member $member;

    public function __construct(
        private NotificationEventSerializer $notificationEventSerializer,
        private Connection $database,
        ApiCache $apiCache
    ) {
        parent::__construct($apiCache);
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            requiresMember: true,
            modelClass: NotificationRecipient::class,
            filterableProperties: ['member_id'],
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $this->filter = [
            'member_id' => $this->member->id,
        ];

        // set the per page to +1 to understand if there are any items down the list
        ++$this->perPage;

        $query = parent::buildQuery($context);
        $query->sort('notification_event_id DESC');

        return $query;
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $this->member = ACLModelRequester::get(); /* @phpstan-ignore-line */

        /** @var NotificationRecipient[] $data */
        $data = parent::buildResponse($context);

        $hasMore = count($data) === $this->perPage;
        $this->response->headers->set('X-Has-More', $hasMore ? '1' : '0');
        // if there is second page - unset the last item, since it was used only
        // as indicator of the next page, and we still need certain number of items for
        // pagination consistency
        if ($hasMore) {
            array_pop($data);
        }
        foreach ($data as $item) {
            $this->notificationEventSerializer->add($item->notification_event);
        }
        $this->database->update('Members', [
            'notification_viewed' => CarbonImmutable::now()->toDateTimeString(),
        ], [
            'id' => $this->member->id,
        ]);

        return $this->notificationEventSerializer->serialize();
    }
}
