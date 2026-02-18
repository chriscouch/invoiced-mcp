<?php

namespace App\ActivityLog\Api;

use App\Core\Authentication\Models\User;
use App\Core\Orm\Query;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\SimpleCache;
use App\ActivityLog\Interfaces\EventStorageInterface;
use App\ActivityLog\Models\Event;
use App\ActivityLog\Models\EventAssociation;
use Aws\S3\Exception\S3Exception;
use Doctrine\DBAL\Connection;
use RuntimeException;

class ListEventsRoute extends AbstractListModelsApiRoute
{
    const FROM_TEAM = 'team';
    const FROM_CUSTOMER = 'customer';
    const FROM_INVOICED = 'invoiced';
    const FROM_API = 'api';

    private ?string $relatedToObject = null;
    private ?string $relatedToId = null;
    private ?string $from = null;
    private ?string $type = null;
    private ?int $startDate = null;
    private ?int $endDate = null;

    public function __construct(
        private readonly EventStorageInterface $eventStorage,
        private readonly Connection $connection,
        private readonly SimpleCache $psr16Cache,
        ApiCache $apiCache
    ) {
        parent::__construct($apiCache);
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Event::class,
            filterableProperties: ['type', 'user_id', 'timestamp'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        // related to
        $relatedTo = explode(',', (string) $context->request->query->get('related_to'));
        if (2 == count($relatedTo)) {
            [$object, $id] = $relatedTo;
            $this->setRelatedTo($object, $id);
        }

        // from
        if ($from = $context->request->query->get('from')) {
            $this->setFrom($from);
        }

        // type
        if ($type = $context->request->query->get('type')) {
            $this->setType($type);
        }

        // date range
        $start = (int) $context->request->query->get('start_date');
        if ($start > 0) {
            $this->setStartDate($start);
        }

        $end = (int) $context->request->query->get('end_date');
        if ($end > 0) {
            $this->setEndDate($end);
        }

        try {
            $events = parent::buildResponse($context);
            $this->eventStorage->hydrateEvents($events);
        } catch (S3Exception) {
            throw new InvalidRequest('Could not retrieve event data from storage.');
        }

        return $events;
    }

    /**
     * Sets the object we are fetching events related to.
     */
    public function setRelatedTo(string $object, string $id): void
    {
        $this->relatedToObject = $object;
        $this->relatedToId = $id;
    }

    /**
     * Sets the originating user to match events to.
     */
    public function setFrom(?string $from): void
    {
        $this->from = $from;
    }

    /**
     * Gets the originating user to match events to.
     */
    public function getFrom(): ?string
    {
        return $this->from;
    }

    /**
     * Sets the object type to match events to.
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * Gets the object type to match events to.
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Sets the start date.
     */
    public function setStartDate(int $date): void
    {
        $this->startDate = $date;
    }

    /**
     * Sets the end date.
     */
    public function setEndDate(int $date): void
    {
        $this->endDate = $date;
    }

    /**
     * Gets the start date.
     */
    public function getStartDate(): ?int
    {
        return $this->startDate;
    }

    /**
     * Gets the end date.
     */
    public function getEndDate(): ?int
    {
        return $this->endDate;
    }

    private function buildPaginationHash(string $qbString, int $offset): string
    {
        return $qbString.$offset;
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        // related to
        if ($this->relatedToObject && $this->relatedToId) {
            try {
                $objectType = ObjectType::fromTypeName($this->relatedToObject);
            } catch (RuntimeException $e) {
                throw new InvalidRequest($e->getMessage());
            }
            $object = $objectType->typeName();
            $objectTypeId = $objectType->value;
            $id = addslashes($this->relatedToId);

            // filter not applied, related to Object
            if (!$this->getFrom() && !$this->getType() && !$this->getStartDate() && !$this->getEndDate() && !$this->getFilter() && !$this->getSort() && isset($context->queryParameters['paginate']) && 'none' == $context->queryParameters['paginate'] && !isset($context->queryParameters['advanced_filter'])) {
                $qb = $this->connection->createQueryBuilder();
                $qb->select('event')
                    ->from('EventAssociations')
                    ->andWhere('object_id = :oid')
                    ->andWhere($qb->expr()->or('object = :o', 'object_type = :ot'))
                    ->setParameters([
                        'oid' => $id,
                        'o' => $object,
                        'ot' => $objectTypeId,
                    ])
                    ->groupBy('event')
                    ->orderBy('event', 'DESC')
                    ->setMaxResults($this->perPage);

                // apply pagination
                $qbString = md5($qb->getSQL().json_encode($qb->getParameters()));
                $offset = $this->perPage * ($this->page - 1);
                $hashKey = $this->buildPaginationHash($qbString, $offset);
                $cursor = $this->psr16Cache->get($hashKey);
                if ($cursor) {
                    $qb->andWhere('event < :lastId')
                        ->setParameter('lastId', $cursor);
                } else {
                    $qb->setFirstResult($this->perPage * ($this->page - 1));
                }
                $eventIds = $qb->fetchFirstColumn();

                if (!$eventIds) {
                    // we have no assotiations, so we always will return empty list
                    return $query->where('1=0');
                }

                if (count($eventIds) === $this->perPage) {
                    $offset = $this->perPage * $this->page;
                    $hashKey = $this->buildPaginationHash($qbString, $offset);
                    $this->psr16Cache->set($hashKey, end($eventIds), 300); // 5 minutes
                }

                // we need to reset pagination, because we already paginated
                $this->page = 1;

                $eventIdString = implode(',', $eventIds);
                $query->where("id IN ($eventIdString)");
            } else {
                $query->join(EventAssociation::class, 'Events.id', 'event')
                    // The object query condition is kept for legacy event rows that do not have object_type
                    ->where("(EventAssociations.object='$object' OR EventAssociations.object_type=$objectTypeId)")
                    ->where('EventAssociations.object_id', $id);
            }
        }

        // type
        if ($this->type) {
            try {
                // special case for payment source which is not an object type
                if ('payment_source' == $this->type) {
                    $cardType = ObjectType::Card;
                    $bankAccountType = ObjectType::BankAccount;
                    $query->where('(object_type_id='.$cardType->value.' OR object_type_id='.$bankAccountType->value.')');
                } else {
                    // check if it is a valid object type
                    $objectType = ObjectType::fromTypeName((string) $this->type);
                    $query->where('object_type_id', $objectType->value);
                }
            } catch (RuntimeException $e) {
                throw new InvalidRequest($e->getMessage());
            }
        }

        // from
        if (self::FROM_TEAM === $this->from) {
            $query->where('user_id', 0, '>');
        } elseif (self::FROM_CUSTOMER === $this->from) {
            $query->where('user_id', -1);
        } elseif (self::FROM_INVOICED === $this->from) {
            $query->where('user_id', User::INVOICED_USER);
        } elseif (self::FROM_API === $this->from) {
            $query->where('user_id', User::API_USER);
        }

        // start date
        if ($this->startDate) {
            $query->where('timestamp', $this->startDate, '>=');
        }

        // end date
        if ($this->endDate) {
            $query->where('timestamp', $this->endDate, '<=');
        }

        $query->with('user_id');
        $query->with('associations');

        return $query;
    }
}
