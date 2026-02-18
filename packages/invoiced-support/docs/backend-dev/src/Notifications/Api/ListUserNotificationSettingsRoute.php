<?php

namespace App\Notifications\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Query;
use App\Notifications\Models\AbstractNotificationEventSetting;
use App\Notifications\Models\NotificationEventCompanySetting;
use App\Notifications\Models\NotificationEventSetting;

/**
 * @extends AbstractListModelsApiRoute<AbstractNotificationEventSetting>
 */
class ListUserNotificationSettingsRoute extends AbstractListModelsApiRoute
{
    private Member $member;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            requiresMember: true,
            filterableProperties: ['member_id'],
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        if ($this->member->allowed('notifications.edit')) {
            $this->filter = [
                'member_id' => $this->member->id,
            ];
        }

        return parent::buildQuery($context);
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $this->member = ACLModelRequester::get(); /* @phpstan-ignore-line */

        if ($this->member->allowed('notifications.edit')) {
            $this->setModelClass(NotificationEventSetting::class);
        } else {
            $this->setModelClass(NotificationEventCompanySetting::class);
        }

        return parent::buildResponse($context);
    }
}
