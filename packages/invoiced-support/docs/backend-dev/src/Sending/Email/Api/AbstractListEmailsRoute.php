<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Email\Models\InboxEmail;
use Doctrine\DBAL\Connection;

/**
 * @extends AbstractListModelsApiRoute<InboxEmail>
 */
abstract class AbstractListEmailsRoute extends AbstractListModelsApiRoute
{
    public function __construct(
        private Connection $connection,
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
            modelClass: InboxEmail::class,
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $emails = parent::buildResponse($context);

        // participants eager loading
        if (in_array('participants', $this->expand)) {
            $qb = $this->connection->createQueryBuilder();
            $participants = $qb->select('ep.*, epa.type, epa.email_id')
                ->from('EmailParticipants', 'ep')
                ->join('ep', 'EmailParticipantAssociations', 'epa', 'ep.id = epa.participant_id')
                ->where($qb->expr()->in('epa.email_id', ':ids'))
                ->setParameter('ids', array_map(fn (InboxEmail $email) => $email->id, $emails), Connection::PARAM_STR_ARRAY)
                ->fetchAllAssociative();
            /** @var InboxEmail $email */
            foreach ($emails as $email) {
                $email->setParticipants(
                    array_filter($participants, fn (array $participant) => $participant['email_id'] == $email->id)
                );
            }
        }

        return $emails;
    }
}
