<?php

namespace App\Companies\Libs;

use App\AccountsReceivable\Models\Invoice;
use App\Companies\Exception\NumberingException;
use App\Companies\Models\AutoNumberSequence;
use App\Companies\Models\Company;
use App\Core\Utils\Enums\ObjectType;
use Doctrine\DBAL\Connection;
use App\Core\Orm\Model;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Manages a document numbering sequence, like invoice numbers.
 *
 * This class operates under these constraints:
 * 1) Document numbers are unique for a given tenant / type (i.e. customers, invoices)
 * 2) Concurrency safe
 * 3) Document numbers can be user-supplied or auto-incremented
 */
class NumberingSequence
{
    const MAX_SEQUENCE_TRIES = 100;

    const RESERVATION_TTL = 100; // seconds

    /** @var AutoNumberSequence[] */
    private static array $sequences = [];
    /** @var self[] */
    private static array $instances = [];
    /** @var LockInterface[] */
    private array $locks = [];

    /**
     * Keeps track of the integer number reserved in next()
     * so it can be updated after the model is saved.
     */
    private int $currentReservation;

    public function __construct(
        private Company $company,
        private ObjectType $type,
        private LockFactory $lockFactory,
        private Connection $database,
    ) {
        self::$instances[] = $this;
    }

    /**
     * Resets all cached numbering sequences including
     * any locks that may be held by this process.
     */
    public static function resetCache(): void
    {
        foreach (self::$instances as $instance) {
            $instance->releaseAll();
        }
        self::$sequences = [];
    }

    /**
     * Reserves a document number in the sequence. This can be used before
     * checking if a number is unique and using it.
     */
    public function reserve(string $number): bool
    {
        return $this->getLock($number)->acquire();
    }

    /**
     * Releases all locks held by this instance.
     */
    public function releaseAll(): void
    {
        foreach ($this->locks as $lock) {
            $lock->release();
        }
        $this->locks = [];
    }

    /**
     * Releases the reservation on a document number. This
     * should only be called if a number was generated
     * or checked on that is not going to be used.
     */
    public function release(string $number): void
    {
        if (isset($this->locks[$number])) {
            $this->getLock($number)->release();
            unset($this->locks[$number]);
        }
    }

    /**
     * Gets the next unused document number in the sequence. The result is
     * guaranteed to be unique and will be reserved for a short period of
     * time. If the number is not used within the expected time window then
     * the reservation will automatically expire. This is done to minimize
     * holes in the numbering system.
     *
     * NOTE: There is a limit on the number of iterations this function will
     * perform before it will stop looking for a unique number.
     *
     * @param bool $reserve whether to reserve the number after it's calculated
     *
     * @throws NumberingException when the next document number cannot be determined
     */
    public function nextNumber(bool $reserve = false): int
    {
        // try up to N next numbers
        $maxTries = self::MAX_SEQUENCE_TRIES;
        $sequenceModel = $this->getModel();
        $next = $sequenceModel->next;
        $iterations = 0;

        do {
            $nextFormatted = $this->applyTemplate($next);
            $unique = $this->isUnique($nextFormatted);
            ++$iterations;

            if (!$unique) {
                ++$next;
            }
        } while ($iterations < $maxTries && !$unique);

        // The iteration max puts an upper bound on how long this function will run.
        if (!$unique) {
            throw new NumberingException("Could not find a unique number after $maxTries iterations");
        }

        // update next number (if it was changed from the original answer)
        if ($next > $sequenceModel->next) {
            $this->setNext($next);
        }

        if ($reserve) {
            $this->currentReservation = $next;
        } else {
            $this->release($nextFormatted);
        }

        return $next;
    }

    /**
     * Gets the next unused document number in the sequence with formatting
     * applied.
     *
     * @throws NumberingException
     *
     * @see nextNumber()
     */
    public function nextNumberFormatted(bool $reserve = false): string
    {
        $next = $this->nextNumber($reserve);

        return $this->applyTemplate($next);
    }

    /**
     * Checks if a formatted number is unique in the series.
     *
     * NOTE: You must pass in an unreserved number. If the number
     * has already been reserved then this will return false.
     */
    public function isUnique(string $number): bool
    {
        // Reserve the number before checking if it is unique.
        // This must be done first in order to prevent
        // race conditions. If we cannot reserve the number then it
        // is assumed to not be unique.
        if (!$this->reserve($number)) {
            return false;
        }

        // Now that the number is reserved we can safely check
        // if there is an object that exists with the given number.
        $modelClass = $this->type->modelClass();

        return 0 == $modelClass::where('number', $number)->count();
    }

    /**
     * Injects a number into the numbering template.
     */
    public function applyTemplate(int $n): string
    {
        return sprintf($this->getModel()->template, $n);
    }

    /**
     * Gets the model behind this sequence.
     */
    public function getModel(): AutoNumberSequence
    {
        $k = $this->type->typeName().$this->company->id();
        if (!isset(self::$sequences[$k])) {
            self::$sequences[$k] = AutoNumberSequence::where('type', $this->type->typeName())->one();
        }

        return self::$sequences[$k];
    }

    /**
     * Sets the next # in the sequence.
     *
     * WARNING: This can only set the number to be higher
     * than the currently stored value. If the number needs
     * to be set to an arbitrary value then you should set
     * it on the model directly instead.
     */
    public function setNext(int $next): void
    {
        $this->getModel()->next = $next;

        // Only update the next number stored in the database if it is
        // greater than the currently stored value. This prevents race
        // conditions where the next number can incorrectly be set lower
        // than it should be.
        $this->database->createQueryBuilder()
            ->update('AutoNumberSequences')
            ->set('next', ':next')
            ->setParameter('next', $next)
            ->andWhere('tenant_id = '.$this->company->id())
            ->andWhere('type = "'.$this->type->typeName().'"')
            ->andWhere('next < '.$next)
            ->executeStatement();
    }

    /**
     * Updates the next number in the sequence after
     * the currently reserved number has been used
     * (because the object was saved).
     */
    public function write(): void
    {
        if (!isset($this->currentReservation)) {
            return;
        }

        $next = $this->currentReservation + 1;
        unset($this->currentReservation);

        $this->setNext($next);

        // We intentionally hold onto the lock here. It will
        // be autoreleased when the instance is destroyed.
        // It cannot be released here because we might be inside
        // of a database transaction and the lock should be maintained
        // until the database transaction is committed.
    }

    /**
     * Builds a lock.
     */
    private function getLock(string $number): LockInterface
    {
        if (!isset($this->locks[$number])) {
            $k = $this->getCacheKey($number);
            $this->locks[$number] = $this->lockFactory->createLock($k, self::RESERVATION_TTL);
        }

        return $this->locks[$number];
    }

    /**
     * Generates the lock reservation key.
     */
    private function getCacheKey(string $number): string
    {
        return 'number_reservation:'.$this->type->typeName().$this->company->id().$number;
    }
}
