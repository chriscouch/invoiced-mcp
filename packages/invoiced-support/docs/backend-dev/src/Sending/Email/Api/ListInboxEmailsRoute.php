<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\InboxEmail;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ListInboxEmailsRoute extends AbstractListEmailsRoute
{
    private int $inboxId;

    public function buildResponse(ApiCallContext $context): array
    {
        $this->inboxId = (int) $context->request->attributes->get('model_id');

        return parent::buildResponse($context);
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: InboxEmail::class,
            filterableProperties: ['thread_id', 'incoming'],
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $restrictionQuery = EmailThread::where('inbox_id', $this->inboxId);

        $query = parent::buildQuery($context);
        $query->where('thread_id IN (SELECT id FROM EmailThreads WHERE '.join(' AND ', $restrictionQuery->getWhere()).')');

        return $query;
    }

    /**
     * Sets up the resolver for the `filter` query parameter.
     */
    public function getFilterOptionsResolver(OptionsResolver $resolver, Options $parent): void
    {
        $resolver->setDefined('incoming');
    }
}
