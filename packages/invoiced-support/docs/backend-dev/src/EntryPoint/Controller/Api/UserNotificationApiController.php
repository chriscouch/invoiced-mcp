<?php

namespace App\EntryPoint\Controller\Api;

use App\Notifications\Api\ConvertUserNotificationsRoute;
use App\Notifications\Api\GetLatestUserNotificationsRoute;
use App\Notifications\Api\ListNotificationEventCompanySettingsRoute;
use App\Notifications\Api\ListUserNotificationSettingsRoute;
use App\Notifications\Api\ListUserNotificationsRoute;
use App\Notifications\Api\SetCompanyNotificationSettingsRoute;
use App\Notifications\Api\SetUserNotificationSettingsRoute;
use App\Notifications\Api\UserNotificationSubscriptionRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class UserNotificationApiController extends AbstractApiController
{
    #[Route(path: '/user_notifications/settings', name: 'set_notification_settings', methods: ['POST'])]
    public function setNotificationSettings(SetUserNotificationSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/user_notifications/settings', name: 'get_notification_settings', methods: ['GET'])]
    public function getNotificationSettings(ListUserNotificationSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/company_notifications/settings', name: 'set_company_notification_settings', methods: ['POST'])]
    public function setCompanyNotificationSettings(SetCompanyNotificationSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/company_notifications/settings', name: 'get_company_notification_settings', methods: ['GET'])]
    public function getCompanyNotificationSettings(ListNotificationEventCompanySettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/company_notifications/users', name: 'convert_company_users', methods: ['POST'])]
    public function convert(ConvertUserNotificationsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/user_notifications', name: 'get_user_notification', methods: ['GET'])]
    public function getNotifications(ListUserNotificationsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/user_notifications/subscription', name: 'user_notification_subscription', methods: ['POST'])]
    public function userNotificationSubscription(UserNotificationSubscriptionRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/user_notifications/latest', name: 'get_latest_user_notification', methods: ['GET'])]
    public function getLatestNotifications(GetLatestUserNotificationsRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
