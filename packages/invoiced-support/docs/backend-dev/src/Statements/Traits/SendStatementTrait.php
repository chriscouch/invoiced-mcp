<?php

namespace App\Statements\Traits;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Statements\Enums\StatementType;
use App\Statements\Libs\StatementBuilder;
use InvalidArgumentException;

/**
 * Trait to be used by the send statement
 * API route classes (email, letter, sms, etc).
 */
trait SendStatementTrait
{
    private StatementBuilder $builder;
    private ?string $type;
    private ?int $start;
    private ?int $end;
    private bool $outstandingOnly;
    private ?string $currency;

    public function parseRequestStatement(ApiCallContext $context): void
    {
        $this->type = (string) $context->request->request->get('type', StatementType::BalanceForward->value);
        $this->start = $context->request->request->getInt('start') ?: null;
        $this->end = $context->request->request->getInt('end') ?: null;
        $this->outstandingOnly = 'past_due' == $context->request->request->get('items');
        $this->currency = ((string) $context->request->request->get('currency')) ?: null;
    }

    public function getStatementParameters(): array
    {
        return [
            'type' => new RequestParameter(
                allowedValues: [StatementType::BalanceForward->value, StatementType::OpenItem->value],
                default: StatementType::BalanceForward->value,
            ),
            'start' => new RequestParameter(
                types: ['integer', 'null'],
                default: null,
            ),
            'end' => new RequestParameter(
                types: ['integer', 'null'],
                default: null,
            ),
            'items' => new RequestParameter(
                allowedValues: ['open', 'past_due'],
                default: 'open',
            ),
            'currency' => new RequestParameter(
                types: ['string', 'null'],
                default: null,
            ),
        ];
    }

    public function getSendModel(): mixed
    {
        $customer = $this->model;
        if (!$customer instanceof Customer) {
            throw new InvalidRequest('Could not find customer');
        }

        $type = StatementType::tryFrom((string) $this->type);
        if (!$type) {
            throw new InvalidArgumentException('Unrecognized statement type: '.$this->type);
        }

        try {
            return $this->builder->build(
                $customer,
                $type,
                $this->currency,
                $this->start,
                $this->end,
                $this->outstandingOnly
            );
        } catch (InvalidArgumentException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
