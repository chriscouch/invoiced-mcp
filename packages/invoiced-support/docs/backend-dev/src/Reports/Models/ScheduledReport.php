<?php

namespace App\Reports\Models;

use App\Companies\Models\Member;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Reports\Exceptions\ReportException;
use App\Reports\Libs\ReportScheduler;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Event\ModelCreating;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int               $id
 * @property SavedReport       $saved_report
 * @property Member            $member
 * @property int               $time_of_day
 * @property string            $frequency
 * @property int               $run_date
 * @property DateTimeInterface $last_run
 * @property DateTimeInterface $next_run
 * @property array             $parameters
 * @property int               $member_id
 */
class ScheduledReport extends MultitenantModel
{
    use AutoTimestamps;

    const COMPANY_LIMIT = 50;

    const FREQUENCY_DAY_OF_WEEK = 'day_of_week';
    const FREQUENCY_DAY_OF_MONTH = 'day_of_month';

    protected static function getProperties(): array
    {
        return [
            'saved_report' => new Property(
                required: true,
                belongs_to: SavedReport::class,
            ),
            'member' => new Property(
                required: true,
                belongs_to: Member::class,
            ),
            'parameters' => new Property(
                type: Type::ARRAY,
            ),
            'time_of_day' => new Property(
                type: Type::INTEGER,
                required: true,
                validate: [['numeric', 'type' => 'int'], ['range', 'min' => 0, 'max' => 23]],
            ),
            'frequency' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['day_of_week', 'day_of_month']],
                default: self::FREQUENCY_DAY_OF_WEEK,
            ),
            'run_date' => new Property(
                type: Type::INTEGER,
            ),
            'last_run' => new Property(
                type: Type::DATETIME,
                null: true,
                default: null,
            ),
            'next_run' => new Property(
                type: Type::DATETIME,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::creating([self::class, 'setCreator']);
        self::saving([self::class, 'calculateNextRun']);
        self::saving([self::class, 'companyLimit']);
    }

    public static function setCreator(ModelCreating $event): void
    {
        /** @var self $report */
        $report = $event->getModel();
        $requester = ACLModelRequester::get();
        if ($requester instanceof Member) {
            $report->member = $requester;
        }
    }

    public static function companyLimit(): void
    {
        if (self::count() > 50) {
            throw new ListenerException('You can not create more than '.self::COMPANY_LIMIT.' scheduled reports per company.');
        }
    }

    public static function calculateNextRun(AbstractEvent $event): void
    {
        /** @var self $scheduledReport */
        $scheduledReport = $event->getModel();

        try {
            if ($event instanceof ModelCreating || ($scheduledReport->dirty('time_of_day', true) || $scheduledReport->dirty('run_date', true) || $scheduledReport->dirty('frequency', true))) {
                $scheduledReport->next_run = ReportScheduler::nextRun($scheduledReport);
            }
        } catch (ReportException $e) {
            throw new ListenerException($e->getMessage(), ['field' => 'next_run']);
        }
    }

    public function getParameters(): array
    {
        $parameters = $this->parameters;
        if (isset($parameters['$dateRange']) && isset($parameters['$dateRange']['period'])) {
            $parameters['$dateRange'] = $this->calculatePeriod($parameters['$dateRange']);
        }

        return $parameters;
    }

    private function calculatePeriod(array $period): array
    {
        if (!isset($period['period'])) {
            return $period;
        }
        $now = CarbonImmutable::now();

        if (is_array($period['period']) && count($period['period'])) {
            if ('days' === $period['period'][0]) {
                $date = $now->sub(...$period['period']);
                $period['start'] = $date->format('Y-m-d');
                $period['end'] = $now->format('Y-m-d');
            }

            return $period;
        }

        switch ($period['period']) {
            case 'today':
                $period['start'] = $period['end'] = $now->format('Y-m-d');
                break;
            case 'yesterday':
                $period['start'] = $period['end'] = $now->subDay()->format('Y-m-d');
                break;
            case 'this_month':
                $period['start'] = $now->firstOfMonth()->format('Y-m-d');
                $period['end'] = $now->lastOfMonth()->format('Y-m-d');
                break;
            case 'last_month':
                $lastMonth = $now->subMonth();
                $period['start'] = $lastMonth->firstOfMonth()->format('Y-m-d');
                $period['end'] = $lastMonth->lastOfMonth()->format('Y-m-d');
                break;
            case 'this_quarter':
                $period['start'] = $now->firstOfQuarter()->format('Y-m-d');
                $period['end'] = $now->lastOfQuarter()->format('Y-m-d');
                break;
            case 'last_quarter':
                $lastQuarter = $now->subQuarter();
                $period['start'] = $lastQuarter->firstOfQuarter()->format('Y-m-d');
                $period['end'] = $lastQuarter->lastOfQuarter()->format('Y-m-d');
                break;
            case 'this_year':
                $period['start'] = $now->firstOfYear()->format('Y-m-d');
                $period['end'] = $now->lastOfYear()->format('Y-m-d');
                break;
            case 'last_year':
                $lastYear = $now->subYear();
                $period['start'] = $lastYear->firstOfYear()->format('Y-m-d');
                $period['end'] = $lastYear->lastOfYear()->format('Y-m-d');
                break;
            case 'all_time':
                $period['end'] = $now->format('Y-m-d');
                break;
            case 'next_90_days':
                $period['start'] = $now->addDay()->format('Y-m-d');
                $period['end'] = $now->addDays(91)->format('Y-m-d');
                break;
        }

        return $period;
    }

    public function toArray(): array
    {
        $result = parent::toArray();

        if ($this->member_id > 0) {
            $result['member'] = $this->member->user;
        } else {
            $result['member'] = null;
        }

        return $result;
    }
}
