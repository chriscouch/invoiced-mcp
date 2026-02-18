<?php

namespace App\EntryPoint\Controller\Api;

use App\SubscriptionBilling\Api\CancelSubscriptionRoute;
use App\SubscriptionBilling\Api\CreateSubscriptionRoute;
use App\SubscriptionBilling\Api\EditSubscriptionRoute;
use App\SubscriptionBilling\Api\ListSubscriptionsRoute;
use App\SubscriptionBilling\Api\PauseSubscriptionRoute;
use App\SubscriptionBilling\Api\RenewManualContractRoute;
use App\SubscriptionBilling\Api\ResumeSubscriptionRoute;
use App\SubscriptionBilling\Api\RetrieveSubscriptionRoute;
use App\SubscriptionBilling\Api\SubscriptionPreviewRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class SubscriptionApiController extends AbstractApiController
{
    #[Route(path: '/subscriptions', name: 'list_subscriptions', methods: ['GET'])]
    public function listAll(ListSubscriptionsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/subscriptions', name: 'create_subscription', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function create(CreateSubscriptionRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/subscriptions/preview', name: 'subscription_preview', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function preview(SubscriptionPreviewRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/subscriptions/{model_id}', name: 'retrieve_subscription', methods: ['GET'])]
    public function retrieve(RetrieveSubscriptionRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/subscriptions/{model_id}', name: 'edit_subscription', methods: ['PATCH'])]
    public function edit(EditSubscriptionRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/subscriptions/{model_id}', name: 'delete_subscription', methods: ['DELETE'])]
    public function delete(CancelSubscriptionRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/subscriptions/{model_id}/renew_contract', name: 'renew_subscription_contract', methods: ['POST'])]
    public function renewContract(RenewManualContractRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/subscriptions/{model_id}/pause', name: 'pause_subscription', methods: ['POST'])]
    public function pauseSubscription(PauseSubscriptionRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/subscriptions/{model_id}/resume', name: 'resume_subscription', methods: ['POST'])]
    public function resumeSubscription(ResumeSubscriptionRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
