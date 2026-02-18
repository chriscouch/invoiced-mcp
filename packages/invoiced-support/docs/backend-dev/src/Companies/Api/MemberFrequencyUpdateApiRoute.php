<?php

namespace App\Companies\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\ACLModelRequester;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Enums\NotificationFrequency;
use App\Notifications\Models\Notification;
use App\Notifications\Models\NotificationEventSetting;

class MemberFrequencyUpdateApiRoute extends AbstractEditModelApiRoute
{
    private ?string $emailUpdateFrequency = null;
    private ?bool $subscribeAll = null;
    private ?bool $enableNewNotifications = null;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Member::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $this->setModelId($context->request->attributes->get('model_id'));

        if ($context->request->request->has('email_update_frequency')) {
            $this->emailUpdateFrequency = (string) $context->request->request->get('email_update_frequency');
        }
        if ($context->request->request->has('subscribe_all')) {
            $this->subscribeAll = $context->request->request->getBoolean('subscribe_all');
        }
        if ($context->request->request->has('notifications')) {
            $this->enableNewNotifications = $context->request->request->getBoolean('notifications');
        }

        $this->retrieveModel($context);

        /** @var Member $member */
        $member = $this->model;

        // if a user requester, they can only update their own notifications
        $requester = ACLModelRequester::get();
        if ($requester instanceof Member && $requester->id != $member->id) {
            throw $this->permissionError();
        }

        if (null !== $this->emailUpdateFrequency) {
            $member->email_update_frequency = $this->emailUpdateFrequency;
        }
        if (null !== $this->subscribeAll) {
            $member->subscribe_all = $this->subscribeAll;
        }
        $memberNotificationsEnabled = $member->notifications;
        if (null !== $this->enableNewNotifications) {
            $member->notifications = $this->enableNewNotifications;
        }
        if ($member->save()) {
            if ($this->enableNewNotifications && !$memberNotificationsEnabled) {
                /** @var Notification[] $oldNotificationsResult */
                $oldNotificationsResult = Notification::where('user_id', $member->user_id)->all();
                $oldNotifications = [];
                $defaultNotifications = [
                    NotificationEventType::PaymentPlanApproved->value,
                    NotificationEventType::ThreadAssigned->value,
                    NotificationEventType::TaskAssigned->value,
                ];
                foreach ($oldNotificationsResult as $old) {
                    $oldNotifications[$old->event] = $old->enabled;
                }
                foreach (NotificationEventSetting::CONVERSION_LIST as $conversion) {
                    if (isset($oldNotifications[$conversion[0]]) && $oldNotifications[$conversion[0]]) {
                        $defaultNotifications[] = $conversion[1]->value;
                    }
                }
                $defaultNotifications = array_unique($defaultNotifications);
                // these items enabled by default
                foreach ($defaultNotifications as $item) {
                    $this->createNotification($member, $item);
                }
            }

            return $member;
        }

        // get the first error
        if ($error = $this->getFirstError()) {
            throw $this->modelValidationError($error);
        }

        // no specific errors available, throw a generic one
        throw new ApiError('There was an error updating the '.$this->getModelName().'.');
    }

    private function createNotification(Member $member, string $type): void
    {
        $setting = new NotificationEventSetting();
        $setting->tenant_id = $member->tenant_id;
        $setting->setNotificationType(NotificationEventType::from($type));
        $setting->member = $member;
        $setting->setFrequency(NotificationFrequency::Instant);
        $setting->saveOrFail();
    }
}
