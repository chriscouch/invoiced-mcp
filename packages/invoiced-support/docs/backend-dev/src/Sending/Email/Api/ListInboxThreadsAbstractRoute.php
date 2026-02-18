<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Orm\Query;
use App\Sending\Email\Models\EmailThread;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * @extends AbstractListModelsApiRoute<EmailThread>
 */
abstract class ListInboxThreadsAbstractRoute extends AbstractListModelsApiRoute
{
    public function __construct(
        protected Connection $connection,
        ApiCache $apiCache
    ) {
        parent::__construct($apiCache);
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: array_merge(
                $this->getBaseQueryParameters(),
                [
                    'status' => new QueryParameter(
                        default: 'all',
                    ),
                ],
            ),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: EmailThread::class,
            filterableProperties: ['inbox_id'],
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        if ('sent' == $context->queryParameters['status']) {
            $query->where('EXISTS (SELECT 1 FROM InboxEmails WHERE thread_id=EmailThreads.id AND incoming=0)');
        } elseif ('all' != $context->queryParameters['status']) {
            $query->where('status', $context->queryParameters['status']);
        }

        return $query;
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $threads = parent::buildResponse($context);

        if ($threads && $this->isParameterIncluded($context, 'cnt')) {
            $ids = array_map(fn ($thread) => $thread->id, $threads);

            $qb = $this->connection->createQueryBuilder();
            $res = $qb->select('count(*) as cnt, thread_id')
                ->from('InboxEmails', 'v')
                ->where($qb->expr()->in('thread_id', ':ids'))
                ->setParameter('ids', $ids, ArrayParameterType::INTEGER)
                ->groupBy('thread_id')
                ->fetchAllAssociative();

            $counters = [];
            foreach ($res as $item) {
                $counters[$item['thread_id']] = $item['cnt'];
            }
            foreach ($threads as $thread) {
                $thread->setCnt($counters[$thread->id] ?? 0);
            }
        }

        return $threads;
    }
}
