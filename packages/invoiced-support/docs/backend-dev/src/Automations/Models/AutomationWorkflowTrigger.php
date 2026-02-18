<?php

namespace App\Automations\Models;

use App\Automations\Enums\AutomationTriggerType;
use App\Automations\ValueObjects\AutomationEvent;
use App\Core\Multitenant\Models\MultitenantModel;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use RRule\RRule;
use DateTimeInterface;

/**
 * @property int                       $id
 * @property AutomationWorkflowVersion $workflow_version
 * @property AutomationTriggerType     $trigger_type
 * @property int|null                  $event_type
 * @property string|null               $r_rule
 * @property DateTimeInterface|null    $last_run
 * @property DateTimeInterface|null    $next_run
 */
class AutomationWorkflowTrigger extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'workflow_version' => new Property(
                required: true,
                in_array: false,
                belongs_to: AutomationWorkflowVersion::class,
            ),
            'trigger_type' => new Property(
                type: Type::ENUM,
                required: true,
                enum_class: AutomationTriggerType::class,
            ),
            'event_type' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'r_rule' => new Property(
                null: true,
            ),
            'last_run' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'next_run' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::saving([self::class, 'saveRRule']);
    }

    public static function saveRRule(AbstractEvent $event): void
    {
        /** @var self $trigger */
        $trigger = $event->getModel();
        if (AutomationTriggerType::Schedule !== $trigger->trigger_type) {
            return;
        }

        if (!$trigger->r_rule) {
            throw new ModelException('Failed to save AutomationWorkflowTrigger: Missing Recurrence Rule for Schedule Trigger');
        }

        if (!$trigger->dirty('r_rule')) {
            return;
        }

        $date = CarbonImmutable::now()->setMinutes(0)->setSecond(0);

        try {
            $rule = new RRule($trigger->r_rule, $date);
        } catch (InvalidArgumentException) {
            throw new ModelException('Invalid Recurrence Rule');
        }

        // occurs now
        $trigger->next_run = $rule->getNthOccurrenceFrom($date, 1);
    }

    public function getNextRun(): ?CarbonImmutable
    {
        return $this->next_run ? CarbonImmutable::parse($this->next_run) : null;
    }

    public function advance(): void
    {
        $this->last_run = $this->next_run;
        $rule = new RRule($this->r_rule, $this->getNextRun()?->startOfHour());

        $nextThreshold = CarbonImmutable::now()->max($this->getNextRun());
        $this->next_run = $rule->getNthOccurrenceAfter($nextThreshold, 1);

        $this->saveOrFail();
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['event_type'] = $this->event_type ? AutomationEvent::fromInteger($this->event_type) : null;

        return $result;
    }
}
