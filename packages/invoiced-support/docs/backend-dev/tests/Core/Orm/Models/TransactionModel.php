<?php

namespace App\Tests\Core\Orm\Models;

use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Model;
use App\Core\Orm\Property;

class TransactionModel extends Model
{
    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
                validate: ['string', 'min' => 5],
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::deleting(function (AbstractEvent $modelEvent) {
            if ('delete fail' == $modelEvent->getModel()->name) { /* @phpstan-ignore-line */
                $modelEvent->stopPropagation();
            }
        });
    }

    protected function usesTransactions(): bool
    {
        return true;
    }
}
