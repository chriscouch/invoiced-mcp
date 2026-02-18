<?php

namespace App\CashApplication\Api;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Response;

class UnsuccessfulCashApplicationMatchesRoute extends AbstractApiRoute implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private Connection $database)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['payments.edit'],
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $groupId1 = (string) $context->request->attributes->get('group_id');

        $paymentId = $this->database->createQueryBuilder()
            ->select('`payment_id`')
            ->from('InvoiceUnappliedPaymentAssociations', 'a')
            ->andWhere('a.group_id = :groupId')
            ->setParameter('groupId', $groupId1)
            ->fetchOne();

        if (!$paymentId) {
            throw new ApiError('No result found for given group id.');
        }

        $this->database->update(
            'InvoiceUnappliedPaymentAssociations',
            [
                '`primary`' => 0,
                '`successful`' => 0,
            ],
            [
                '`group_id`' => $groupId1,
            ]
        );

        $groupId = $this->database->createQueryBuilder()
            ->select('`group_id`')
            ->from('InvoiceUnappliedPaymentAssociations', 'a')
            ->andWhere('a.payment_id = :paymentId')
            ->setParameter('paymentId', $paymentId)
            ->andWhere('a.group_id <> :groupId')
            ->setParameter('groupId', $groupId1)
            ->andWhere('a.successful is NULL')
            ->fetchOne();

        $this->database->update(
            'InvoiceUnappliedPaymentAssociations',
            [
                '`primary`' => 1,
            ],
            [
                '`group_id`' => $groupId,
            ]
        );

        return new Response('', 201);
    }
}
