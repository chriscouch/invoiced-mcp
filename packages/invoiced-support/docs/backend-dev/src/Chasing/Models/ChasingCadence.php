<?php

namespace App\Chasing\Models;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Event\ModelUpdated;
use App\Core\Orm\Event\ModelUpdating;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use App\Core\Utils\ValueObjects\Interval;
use App\SubscriptionBilling\Libs\DateSnapper;
use Carbon\CarbonImmutable;
use Exception;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * Model for representing chasing cadences.
 *
 * @property int         $time_of_day
 * @property string      $frequency
 * @property int|null    $run_date
 * @property string|null $run_days
 * @property int         $last_run
 * @property int|null    $next_run
 * @property bool        $paused
 * @property float|null  $min_balance
 * @property string      $assignment_mode
 * @property string      $assignment_conditions
 * @property array       $steps
 */
class ChasingCadence extends AbstractChasingCadence
{
    use ApiObjectTrait;

    const FREQUENCY_DAILY = 'daily';
    const FREQUENCY_DAY_OF_WEEK = 'day_of_week';
    const FREQUENCY_DAY_OF_MONTH = 'day_of_month';

    const ASSIGNMENT_MODE_NONE = 'none';
    const ASSIGNMENT_MODE_DEFAULT = 'default';
    const ASSIGNMENT_MODE_CONDITIONAL = 'conditions';

    private const COMPANY_LIMIT = 100;

    /** @var ChasingCadenceStep[] */
    private ?array $_steps = null;
    private bool $_saveSteps = false;

    protected static function getProperties(): array
    {
        return [
            'time_of_day' => new Property(
                type: Type::INTEGER,
                required: true,
                validate: [['numeric', 'type' => 'int'], ['range', 'min' => 0, 'max' => 23]],
            ),
            'frequency' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['daily', 'day_of_week', 'day_of_month']],
                default: self::FREQUENCY_DAILY,
            ),
            'run_date' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'run_days' => new Property(
                null: true,
            ),
            'last_run' => new Property(
                type: Type::DATE_UNIX,
                default: 0,
            ),
            'next_run' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'paused' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'min_balance' => new Property(
                type: Type::FLOAT,
                null: true,
            ),
            'assignment_mode' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['none', 'default', 'conditions']],
                default: 'none',
            ),
            'assignment_conditions' => new Property(),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::saving([self::class, 'validateAssignmentConditions']);
        self::creating([self::class, 'companyLimit']);
        self::saving([self::class, 'captureSteps']);
        self::saving([self::class, 'calculateNextRun']);
        self::saved([self::class, 'saveSteps']);
        self::saved([self::class, 'makeDefault']);
        self::deleting([self::class, 'canDelete']);
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;

        // expand relationships
        $result['steps'] = [];
        foreach ($this->getSteps() as $step) {
            $result['steps'][] = $step->toArray();
        }

        return $result;
    }

    public static function companyLimit(): void
    {
        if (self::count() > self::COMPANY_LIMIT) {
            throw new ListenerException('You can not create more than '.self::COMPANY_LIMIT.' customer chasing cadences per company.');
        }
    }

    public static function validateAssignmentConditions(AbstractEvent $event): void
    {
        /** @var static $cadence */
        $cadence = $event->getModel();

        if (self::ASSIGNMENT_MODE_CONDITIONAL != $cadence->assignment_mode) {
            return;
        }

        $expressionLanguage = new ExpressionLanguage();

        try {
            $expressionLanguage->compile($cadence->assignment_conditions, ['customer']);
        } catch (SyntaxError $e) {
            throw new ListenerException('Invalid assignment formula: '.$e->getMessage(), ['field' => 'assignment_conditions']);
        }
    }

    /**
     * Captures and validates the steps being saved on the schedule.
     */
    public static function captureSteps(AbstractEvent $event, string $eventName): void
    {
        /** @var static $cadence */
        $cadence = $event->getModel();
        $isUpdate = ModelUpdating::getName() == $eventName;
        $stepsInput = null;
        if (isset($cadence->steps)) {
            $stepsInput = $cadence->steps;
            unset($cadence->steps);
        }

        if ($isUpdate) {
            if (!is_array($stepsInput)) {
                return;
            }

            $n = Customer::where('chasing_cadence_id', $cadence->id())->count();
            if ($n > 0) {
                $originalCadenceSteps = $cadence->getSteps();
                if (count($stepsInput) !== count($originalCadenceSteps)) {
                    throw new ListenerException('You cannot edit the number of steps in a chasing cadence when there are customers assigned to the cadence. Please remove the customers assigned to this cadence or create a new cadence to add or delete steps.', ['field' => 'steps']);
                }
                foreach ($originalCadenceSteps as $i => $step) {
                    $stepsInput[$i]['id'] = $step->id();

                    if (isset($stepsInput[$i]['schedule']) && $step->schedule !== $stepsInput[$i]['schedule']) {
                        throw new ListenerException('You cannot edit the schedule of steps in a chasing cadence when there are customers assigned to the cadence. Please remove the customers assigned to this cadence or create a new cadence to modify the step schedule.', ['field' => 'steps.schedule']);
                    }
                }
            }
        }
        $steps = [];
        if (is_array($stepsInput)) {
            foreach ($stepsInput as $i => $stepParams) {
                if (!is_array($stepParams)) {
                    continue;
                }

                if (!isset($stepParams['id'])) {
                    $step = new ChasingCadenceStep();
                } else {
                    $step = ChasingCadenceStep::findOrFail($stepParams['id']);
                }

                foreach ($stepParams as $k => $v) {
                    $step->$k = $v;
                }

                if ($step->repeating && $i < count($stepsInput) - 1) { /* @phpstan-ignore-line */
                    throw new ListenerException('Repeaters can only be used as the last step in a schedule.', ['field' => 'steps']);
                }

                $steps[] = $step;
            }
        }

        $cadence->_saveSteps = true;
        $cadence->_steps = $steps;

        if (0 === count($steps)) {
            throw new ListenerException('Cadences must have at least one chasing step. Please add at least one step to the schedule before continuing.', ['field' => 'steps']);
        }
    }

    public static function calculateNextRun(AbstractEvent $event): void
    {
        /** @var static $cadence */
        $cadence = $event->getModel();

        try {
            $cadence->next_run = self::nextRun($cadence);
        } catch (Exception $e) {
            throw new ListenerException($e->getMessage(), ['field' => 'next_run']);
        }
    }

    /**
     * @throws Exception
     */
    public static function nextRun(ChasingCadence $cadence, ?int $currentTime = null): ?int
    {
        if ($cadence->paused) {
            return null;
        }

        if (!$currentTime) {
            $currentTime = time();
        }

        // IMPORTANT! Make sure the next run is scheduled
        // in the merchant's native time zone
        $cadence->tenant()->useTimezone();
        $nextRun = (int) mktime($cadence->time_of_day, 0, 0, (int) date('n', $currentTime), (int) date('j', $currentTime), (int) date('Y', $currentTime));

        // if the calculated next run is not after the last run
        // or if it's in the past and has not run before then move
        // it to tomorrow
        if (self::FREQUENCY_DAILY == $cadence->frequency) {
            $lastRun = $cadence->last_run;
            if ($nextRun <= $lastRun || (!$lastRun && $nextRun < $currentTime)) {
                $nextRun += 86400; // add 1 day
            }

            return $nextRun;
        }

        if (self::FREQUENCY_DAY_OF_WEEK == $cadence->frequency) {
            $interval = new Interval(1, 'week');
            $snapper = new DateSnapper($interval);
            $runDays = explode(',', $cadence->run_days ?: (string) $cadence->run_date);
            $runCandidates = [];
            foreach ($runDays as $day) {
                $day = (int) $day;
                if ($day < 1 || $day > 7) {
                    throw new Exception('Run day invalid');
                }
                $runCandidates[] = $snapper->next($day, CarbonImmutable::createFromTimestamp($nextRun))->getTimestamp();
            }
            $nextRun = min($runCandidates);

            // the date snapper will set the time to 00:00
            // so the time needs to be set on the date
            return (int) mktime($cadence->time_of_day, 0, 0, (int) date('n', $nextRun), (int) date('j', $nextRun), (int) date('Y', $nextRun));
        }

        if (self::FREQUENCY_DAY_OF_MONTH == $cadence->frequency) {
            $runDate = $cadence->run_date;
            if ($runDate < 1 || $runDate > 31) {
                throw new Exception('Run date must be between 1 and 31');
            }

            $interval = new Interval(1, 'month');
            $snapper = new DateSnapper($interval);
            $nextRun = $snapper->next($runDate, CarbonImmutable::createFromTimestamp((int) $nextRun))->getTimestamp();

            // the date snapper will set the time to 00:00
            // so the time needs to be set on the date
            return (int) mktime($cadence->time_of_day, 0, 0, (int) date('n', $nextRun), (int) date('j', $nextRun), (int) date('Y', $nextRun));
        }

        throw new Exception('Unsupported frequency: '.$cadence->frequency);
    }

    public static function saveSteps(AbstractEvent $event, string $eventName): void
    {
        /** @var static $cadence */
        $cadence = $event->getModel();

        if (!$cadence->_saveSteps) {
            return;
        }

        $isUpdate = ModelUpdated::getName() == $eventName;

        $order = 1;
        $ids = [];
        foreach ($cadence->getSteps() as $step) {
            $step->order = $order;
            if (!$step->persisted()) {
                $step->chasing_cadence_id = (int) $cadence->id();
            }

            if (!$step->save()) {
                $cadence->_steps = null;
                throw new ListenerException('Could not save chasing cadence steps: '.$step->getErrors(), ['field' => 'steps']);
            }

            ++$order;
            $ids[] = $step->id();
        }

        $cadence->_saveSteps = false;

        // remove deleted steps
        if ($isUpdate && count($ids) > 0) {
            self::getDriver()->getConnection(null)->createQueryBuilder()
                ->delete('ChasingCadenceSteps')
                ->andWhere('tenant_id = '.$cadence->tenant_id)
                ->andWhere('chasing_cadence_id = '.$cadence->id())
                ->andWhere('id NOT IN ('.implode(',', $ids).')')
                ->executeStatement();
        }
    }

    /**
     * @param ChasingCadenceStep[] $steps
     */
    public function hydrateSteps(array $steps): void
    {
        $this->_steps = $steps;
        $this->_saveSteps = false;
    }

    public static function canDelete(AbstractEvent $event): void
    {
        /** @var static $cadence */
        $cadence = $event->getModel();
        $n = Customer::where('chasing_cadence_id', $cadence->id())->count();
        if ($n > 0) {
            throw new ListenerException('You cannot delete a chasing cadence when there are customers assigned to the cadence. Please remove the customers assigned to this cadence before deleting.');
        }
    }

    /**
     * Gets the steps for this chasing cadence.
     *
     * @return ChasingCadenceStep[]
     */
    public function getSteps(): array
    {
        if ($this->_steps) {
            return $this->_steps;
        }

        if ($this->hasId()) {
            $this->_steps = ChasingCadenceStep::where('chasing_cadence_id', $this->id())
                ->sort('order ASC')
                ->first(100);
        } else {
            return [];
        }

        return $this->_steps;
    }

    public static function makeDefault(AbstractEvent $event): void
    {
        /** @var static $cadence */
        $cadence = $event->getModel();

        // there can only be one default cadence
        if (self::ASSIGNMENT_MODE_DEFAULT == $cadence->assignment_mode) {
            ChasingCadence::where('id', $cadence->id(), '<>')
                ->where('assignment_mode', self::ASSIGNMENT_MODE_DEFAULT)
                ->set(['assignment_mode' => self::ASSIGNMENT_MODE_NONE]);
        }
    }

    //
    // Accessors
    //

    /**
     * Gets the number of customers on this cadence.
     */
    protected function getNumCustomersValue(): int
    {
        return Customer::where('chasing_cadence_id', $this->id)->count();
    }
}
