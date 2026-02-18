<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Discount;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\AccountsReceivable\Models\Shipping;
use App\AccountsReceivable\Models\Tax;
use App\Chasing\Models\PromiseToPay;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Utils\Enums\ObjectType;
use App\Reports\ValueObjects\AgingBreakdown;

class ListInvoicesRoute extends ListDocumentsRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Invoice::class,
            filterableProperties: ['network_document'],
            features: ['accounts_receivable'],
        );
    }

    protected function getOptions(ApiCallContext $context): array
    {
        $options = parent::getOptions($context);
        $dateColumn = $context->queryParameters['date_column'] ?? null;
        if (in_array($dateColumn, [AgingBreakdown::BY_DUE_DATE, AgingBreakdown::BY_DATE])) {
            $options['date_column'] = $dateColumn;
        }
        // tags
        if ($tags = $context->queryParameters['tags'] ?? []) {
            // validate tags
            $filtered = [];
            foreach ($tags as $tag) {
                // Allowed characters: a-z, A-Z, 0-9, _, -
                // Min length: 1
                if (!preg_match('/^[a-z0-9_-]+$/i', $tag)) {
                    continue;
                }

                // max of 50 chars.
                $filtered[] = substr($tag, 0, Invoice::TAG_LENGTH);
            }

            $options['tags'] = $filtered;
        }

        $options['payment_plan'] = $context->queryParameters['payment_plan'] ?? null;
        $options['payment_attempted'] = $context->queryParameters['payment_attempted'] ?? null;
        $options['broken_promises'] = $context->queryParameters['broken_promises'] ?? null;
        $options['customer_payment_info'] = $context->queryParameters['customer_payment_info'] ?? null;
        $options['total'] = $context->queryParameters['total'] ?? null;
        $options['balance'] = $context->queryParameters['balance'] ?? null;
        if (isset($context->queryParameters['chasing'])) {
            $options['chasing'] = $context->queryParameters['chasing'];
        }
        $options['cadence'] = $context->queryParameters['cadence'] ?? null;

        return $options;
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $models = parent::buildResponse($context);

        if (0 == count($models)) {
            return $models;
        }

        $ids = [];
        foreach ($models as $model) {
            $ids[] = $model->id;
        }

        if (!$this->isParameterExcluded($context, 'items')) {
            $this->eagerLoadLineItems($models, $ids);
        }
        if (!$this->isParameterExcluded($context, 'rates')) {
            $this->eagerLoadDiscounts('invoice_id', $models, $ids);
            $this->eagerLoadTaxes('invoice_id', $models, $ids);
            $this->eagerLoadShipping('invoice_id', $models, $ids);
        }
        if (!$this->isParameterExcluded($context, 'metadata')) {
            $this->eagerLoadMetadata(ObjectType::Invoice, $models, $ids);
        }

        if ($this->isParameterIncluded($context, 'expected_payment_date')) {
            return $this->withExpectedPaymentDates($models);
        }

        return $models;
    }

    protected function withExpectedPaymentDates(array $invoices): array
    {
        $invoiceIds = [];
        foreach ($invoices as $invoice) {
            if (!$invoice->paid && !$invoice->closed && !$invoice->draft) {
                $invoiceIds[] = $invoice->id();
            }
        }

        $mapping = [];

        if (count($invoiceIds) > 0) {
            $expectedPaymentDates = PromiseToPay::where('invoice_id', $invoiceIds)->all();
            foreach ($expectedPaymentDates as $expectedPaymentDate) {
                $mapping[$expectedPaymentDate->invoice_id] = $expectedPaymentDate->toArray();
            }
        }

        foreach ($invoices as $invoice) {
            if (isset($mapping[$invoice->id()])) {
                $invoice->expected_payment_date = $mapping[$invoice->id()];
            } else {
                $invoice->expected_payment_date = false;
            }
        }

        return $invoices;
    }

    /**
     * @param Invoice[] $models
     */
    protected function eagerLoadLineItems(array $models, array $ids): void
    {
        /** @var LineItem[][] $lineItems */
        $lineItems = $this->getChildren(LineItem::class, 'invoice_id', $ids);

        foreach ($models as $model) {
            $id = $model->id;
            if (isset($lineItems[$id])) {
                $lineItemIds = [];
                foreach ($lineItems[$id] as $lineItem) {
                    $lineItemIds[] = $lineItem->id;
                }

                $this->eagerLoadDiscounts('line_item_id', $lineItems[$id], $lineItemIds);
                $this->eagerLoadTaxes('line_item_id', $lineItems[$id], $lineItemIds);
                $this->eagerLoadMetadata(ObjectType::LineItem, $lineItems[$id], $lineItemIds);

                $model->setLineItems($lineItems[$id]);
            } else {
                $model->setLineItems([]);
            }
        }
    }

    /**
     * @param Invoice[]|LineItem[] $models
     */
    protected function eagerLoadDiscounts(string $parentColumn, array $models, array $ids): void
    {
        $discounts = $this->getChildren(Discount::class, $parentColumn, $ids);
        foreach ($models as $model) {
            $id = $model->id;
            if (isset($discounts[$id])) {
                $model->setDiscounts($discounts[$id]);
            } else {
                $model->setDiscounts([]);
            }
        }
    }

    /**
     * @param Invoice[]|LineItem[] $models
     */
    protected function eagerLoadTaxes(string $parentColumn, array $models, array $ids): void
    {
        $taxes = $this->getChildren(Tax::class, $parentColumn, $ids);
        foreach ($models as $model) {
            $id = $model->id;
            if (isset($taxes[$id])) {
                $model->setTaxes($taxes[$id]);
            } else {
                $model->setTaxes([]);
            }
        }
    }

    protected function eagerLoadShipping(string $parentColumn, array $models, array $ids): void
    {
        // DEPRECATED
        if (!$this->tenant->get()->features->has('shipping')) {
            return;
        }

        $shipping = $this->getChildren(Shipping::class, $parentColumn, $ids);
        foreach ($models as $model) {
            $id = $model->id;
            if (isset($shipping[$id])) {
                $model->setShipping($shipping[$id]);
            } else {
                $model->setShipping([]);
            }
        }
    }

    /**
     * @param Invoice[]|LineItem[] $models
     */
    protected function eagerLoadMetadata(ObjectType $parentType, array $models, array $ids): void
    {
        $data = $this->database->createQueryBuilder()
            ->select('`key`,`value`,object_id')
            ->from('Metadata')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->tenant->get()->id())
            ->andWhere('object_type = :objectType')
            ->setParameter('objectType', $parentType->typeName())
            ->andWhere('object_id IN ('.implode(',', $ids).')')
            ->fetchAllAssociative();

        $result = [];
        foreach ($data as $row) {
            $parentId = $row['object_id'];
            if (!isset($result[$parentId])) {
                $result[$parentId] = new \stdClass();
            }
            $key = $row['key'];
            $result[$parentId]->$key = $row['value'];
        }

        foreach ($models as $model) {
            $id = $model->id;
            if (isset($result[$id])) {
                $model->hydrateMetadata($result[$id]);
            } else {
                $model->hydrateMetadata(new \stdClass());
            }
        }
    }

    /**
     * @param class-string $modelClass
     */
    protected function getChildren(string $modelClass, string $foreignId, array $ids, array $queryParams = []): array
    {
        $in = $foreignId.' IN ('.implode(',', $ids).')';
        $query = $modelClass::where($in)
            ->sort('order ASC,id ASC');
        foreach ($queryParams as $k => $v) {
            $query->where($k, $v);
        }

        $children = $query->limit(1000)->all();

        $result = [];
        foreach ($children as $child) {
            $parentId = $child->$foreignId;
            if (!isset($result[$parentId])) {
                $result[$parentId] = [];
            }
            $result[$parentId][] = $child;
        }

        return $result;
    }
}
