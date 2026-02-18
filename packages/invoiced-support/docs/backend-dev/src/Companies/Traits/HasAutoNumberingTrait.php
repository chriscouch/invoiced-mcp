<?php

namespace App\Companies\Traits;

use App\Companies\Exception\NumberingException;
use App\Companies\Libs\NumberingSequence;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\LockFactoryFacade;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\DriverException;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Utils\Enums\ObjectType;
use ICanBoogie\Inflector;

/**
 * @property string $number
 */
trait HasAutoNumberingTrait
{
    private NumberingSequence $numberingSequence;

    /**
     * Installs the auto numbering trait on the model.
     */
    protected function autoInitializeAutoNumbering(): void
    {
        // Document # should be assigned before the document is calculated
        self::creating([static::class, 'generateNumbering'], 1);

        // install the event listener for this model
        // We would prefer the numbering check to run last
        // so that if the save operation fails we do not have
        // to reserve/release the number. This is a performance optimization.
        self::saved([static::class, 'updateNumberingSequence'], -256);
    }

    /**
     * Generates and verifies the model's unique number.
     */
    public static function generateNumbering(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $sequence = $model->getNumberingSequence();
        // generate or validate the object number
        try {
            $number = $model->number;
            if (!$number) {
                $model->number = $sequence->nextNumberFormatted(true);
            } elseif (!$sequence->isUnique($number)) {
                throw new ListenerException(self::getUniqueNumberError($number), ['duplicate_number' => true, 'field' => 'number']);
            }
        } catch (NumberingException $e) {
            throw new ListenerException($e->getMessage(), ['field' => 'number']);
        }
    }

    private static function getUniqueNumberError(string $number): string
    {
        $name = strtolower(Inflector::get()->titleize(static::modelName()));

        return 'The given '.$name.' number has already been taken: '.$number;
    }

    /**
     * Updates the auto-numbering sequence.
     */
    public static function updateNumberingSequence(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $model->getNumberingSequence()->write();
    }

    /**
     * Gets the numbering sequence for this model.
     */
    public function getNumberingSequence(): NumberingSequence
    {
        if (!isset($this->numberingSequence)) {
            $objectType = ObjectType::fromModel($this);
            $this->numberingSequence = new NumberingSequence($this->tenant(), $objectType, LockFactoryFacade::get(), self::getDriver()->getConnection(null));
        }

        return $this->numberingSequence;
    }

    public function create(array $data = []): bool
    {
        // We are wrapping create() because we need to release
        // the document number if the save fails. UNLESS, the
        // save failed because the number was already reserved,
        // in which case we do not want to release the reservation.
        try {
            $saved = parent::create($data);
        } catch (DriverException $e) {
            if (strpos($e->getMessage(), 'unique_number')) {
                throw new InvalidRequest(self::getUniqueNumberError($data['number'] ?? $this->number ?? ''), 400, 'number');
            }

            throw $e;
        }
        $number = $this->number;
        if (!$saved && $number && !$this->getErrors()->has(true, 'duplicate_number')) {
            $this->getNumberingSequence()->release($number);
        }

        return $saved;
    }

    public function set(array $data = []): bool
    {
        // We are wrapping set() because we need to release
        // the document number if the save fails. UNLESS, the
        // save failed because the number was already reserved,
        // in which case we do not want to release the reservation.
        $saved = parent::set($data);
        $number = $this->number;
        if (!$saved && $number && !$this->getErrors()->has(true, 'duplicate_number')) {
            $this->getNumberingSequence()->release($number);
        }

        return $saved;
    }
}
