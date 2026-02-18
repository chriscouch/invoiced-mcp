<?php

namespace App\Notifications\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\ACLModelRequester;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Enums\NotificationFrequency;
use App\Notifications\Models\NotificationEventSetting;

class SetUserNotificationSettingsRoute extends AbstractApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            requiresMember: true,
        );
    }

    /**
     * @return NotificationEventSetting[]
     */
    public function buildResponse(ApiCallContext $context): array
    {
        /** @var Member $member */
        $member = ACLModelRequester::get();
        $settings = $context->request->request->all('settings');

        $response = [];
        /** @var NotificationEventSetting[] $savedSettings */
        $savedSettings = NotificationEventSetting::where('member_id', $member->id)->execute();
        foreach ($settings as $setting) {
            $settingModel = current(array_filter($savedSettings, fn (NotificationEventSetting $el) => (isset($setting['id']) && $el->id == $setting['id']) || $setting['notification_type'] === $el->getNotificationType()->value));
            if (!$settingModel) {
                $settingModel = new NotificationEventSetting();
                $settingModel->member = $member;
            }
            $settingModel->setNotificationType(NotificationEventType::from($setting['notification_type']));
            $settingModel->setFrequency(NotificationFrequency::from($setting['frequency']));
            $settingModel->saveOrFail();
            $response[] = $settingModel;
        }

        return $response;
    }
}
