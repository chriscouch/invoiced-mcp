<?php

namespace App\Automations\ValueObjects;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Statements\Libs\StatementBuilder;
use Carbon\CarbonImmutable;

class SendDocumentActionSettings extends AbstractActionSettings
{
    public function __construct(
        public string $template,
        public ?string $type,
        public ?string $period,
        public ?string $openItemMode,
    ) {
    }

    public function getSendableDocument(MultitenantModel $sourceObject, StatementBuilder $builder): ?SendableDocumentInterface
    {
        if ($sourceObject instanceof Customer) {
            if ('balance_forward' === $this->type) {
                $now = CarbonImmutable::now();
                /**
                 * @var ?CarbonImmutable $start
                 * @var ?CarbonImmutable $end
                 */
                [$start, $end] = match ($this->period) {
                    'this_month' => [$now->startOfMonth(), $now->endOfDay()],
                    'last_month' => [$now->subMonth()->startOfMonth(), $now->subMonth()->endOfMonth()],
                    'this_quarter' => [$now->startOfQuarter(), $now->endOfDay()],
                    'last_quarter' => [$now->subQuarter()->startOfQuarter(), $now->subQuarter()->endOfQuarter()],
                    'this_year' => [$now->startOfYear(), $now->endOfDay()],
                    'last_year' => [$now->subYear()->startOfYear(), $now->subYear()->endOfYear()],
                    default => [null, null],
                };

                return $builder->balanceForward($sourceObject, null, $start?->unix(), $end?->unix());
            }

            return $builder->openItem($sourceObject, null, null, 'past_due' === $this->openItemMode);
        } elseif ($sourceObject instanceof Invoice || $sourceObject instanceof Estimate) {
            return $sourceObject;
        }

        return null;
    }
}
