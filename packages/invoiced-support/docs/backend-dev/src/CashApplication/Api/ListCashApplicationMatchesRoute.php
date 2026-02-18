<?php

namespace App\CashApplication\Api;

use App\AccountsReceivable\Models\Invoice;
use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use Doctrine\DBAL\Connection;

class ListCashApplicationMatchesRoute extends AbstractModelApiRoute
{
    public function __construct(private Connection $database)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Invoice::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $paymentId = (int) $context->request->attributes->get('payment_id');

        $associations = [];

        $results = $this->database->createQueryBuilder()
            ->select('`invoice_id`, `short_pay`, `group_id`, `is_remittance_advice`, `certainty`')
            ->from('InvoiceUnappliedPaymentAssociations', 'a')
            ->andWhere('a.payment_id = :paymentId')
            ->setParameter('paymentId', $paymentId)
            ->andWhere('a.primary = 1')
            ->fetchAllAssociative();

        foreach ($results as $row) {
            $invoice = Invoice::find($row['invoice_id']);

            if (!$invoice) {
                throw new ApiError('Could not find invoice matched with given payment.');
            }

            $associations[] = [
                'invoice' => $invoice->toArray(),
                'short_pay' => (bool) $row['short_pay'],
                'customer' => $invoice->customer()->toArray(),
                'group_id' => $row['group_id'],
                'is_remittance_advice' => (bool) $row['is_remittance_advice'],
                'certainty' => $row['certainty'],
            ];
        }

        $count = $this->database->createQueryBuilder()
            ->select('count(*)')
            ->from('InvoiceUnappliedPaymentAssociations', 'a')
            ->andWhere('a.payment_id = :paymentId')
            ->setParameter('paymentId', $paymentId)
            ->andWhere('a.primary = 0')
            ->andWhere('a.successful is NULL')
            ->fetchOne();

        $associations[] = ['hasNextMatch' => $count > 0 ? true : false];

        return $associations;
    }
}
