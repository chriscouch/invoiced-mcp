<?php

namespace App\EntryPoint\Controller\Api;

use App\ActivityLog\Api\ListEventsRoute;
use App\ActivityLog\Api\RetrieveEventRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class EventApiController extends AbstractApiController
{
    #[Route(path: '/events', name: 'list_events', methods: ['GET'])]
    public function listAll(ListEventsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/events/{model_id}', name: 'retrieve_event', methods: ['GET'])]
    public function retrieve(RetrieveEventRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
