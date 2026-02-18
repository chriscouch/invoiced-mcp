<?php

namespace App\Notifications\Api;

use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Enums\NotificationFrequency;
use App\Notifications\Models\NotificationEventCompanySetting;

class SetCompanyNotificationSettingsRoute extends AbstractApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'settings' => new RequestParameter(
                    required: true,
                    types: ['array'],
                ),
            ],
            requiredPermissions: ['business.admin'],
        );
    }

    /**
     * @return NotificationEventCompanySetting[]
     */
    public function buildResponse(ApiCallContext $context): array
    {
        $response = [];
        /** @var NotificationEventCompanySetting[] $savedSettings */
        $savedSettings = NotificationEventCompanySetting::query()->execute();
        foreach ($context->requestParameters['settings'] as $setting) {
            $settingModel = current(array_filter($savedSettings, fn (NotificationEventCompanySetting $el) => (isset($setting['id']) && $el->id == $setting['id']) || $setting['notification_type'] === $el->getNotificationType()->value));
            if (!$settingModel) {
                $settingModel = new NotificationEventCompanySetting();
            }
            $settingModel->setNotificationType(NotificationEventType::from($setting['notification_type']));
            $settingModel->setFrequency(NotificationFrequency::from($setting['frequency']));
            $settingModel->saveOrFail();
            $response[] = $settingModel;
        }

        return $response;
    }
}
