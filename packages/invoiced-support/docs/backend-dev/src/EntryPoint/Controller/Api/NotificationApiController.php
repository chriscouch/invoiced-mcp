<?php

namespace App\EntryPoint\Controller\Api;

use App\Notifications\Api\CreateNotificationRoute;
use App\Notifications\Api\DeleteNotificationRoute;
use App\Notifications\Api\EditNotificationRoute;
use App\Notifications\Api\ListNotificationsRoute;
use App\Notifications\Api\RetrieveNotificationRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class NotificationApiController extends AbstractApiController
{
    #[Route(path: '/notifications', name: 'list_notifications', methods: ['GET'])]
    public function listAll(ListNotificationsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/notifications', name: 'create_notification', methods: ['POST'])]
    public function create(CreateNotificationRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/notifications/{model_id}', name: 'retrieve_notification', methods: ['GET'])]
    public function retrieve(RetrieveNotificationRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/notifications/{model_id}', name: 'edit_notification', methods: ['PATCH'])]
    public function edit(EditNotificationRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/notifications/{model_id}', name: 'delete_notification', methods: ['DELETE'])]
    public function delete(DeleteNotificationRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
