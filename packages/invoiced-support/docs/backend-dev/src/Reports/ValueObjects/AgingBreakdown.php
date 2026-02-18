<?php

namespace App\Reports\ValueObjects;

use App\AccountsPayable\Models\AccountsPayableSettings;
use App\AccountsReceivable\Models\AccountsReceivableSettings;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Manages an aging breakdown definition and provides support
 * around using aging breakdowns. Accepts an input as an array
 * of lower bounds, i.e. `[0, 7, 14, 30, 60]`.
 */
final class AgingBreakdown
{
    const BY_DATE = 'date';
    const BY_DUE_DATE = 'due_date';

    private const AGING_COLORS = [
        1 => '#54BF83',
        2 => '#BFED1A',
        3 => '#E7ED1A',
        4 => '#e91c2b',
        5 => '#9E0510',
    ];

    private array $buckets = [];

    public function __construct(array $buckets, public readonly string $dateColumn)
    {
        foreach ($buckets as $i => $lower) {
            // The "Current" bucket when the date column is a special case
            if (-1 == $lower) {
                $upper = null;
            } else {
                $upper = $buckets[$i + 1] ?? null;
            }

            $this->buckets[] = [
                'lower' => $lower,
                'upper' => $upper ? $upper - 1 : null,
                'color' => $this->generateColor($i, count($buckets)),
            ];
        }

        if (self::BY_DATE != $dateColumn && self::BY_DUE_DATE != $dateColumn) {
            throw new \InvalidArgumentException('Invalid date column: '.$dateColumn);
        }
    }

    public static function fromSettings(AccountsReceivableSettings|AccountsPayableSettings $settings): self
    {
        return new self($settings->aging_buckets, $settings->aging_date);
    }

    /**
     * Gets the aging buckets.
     *
     * @return array[]
     */
    public function getBuckets(): array
    {
        return $this->buckets;
    }

    /**
     * Gets the name of an age range.
     */
    public function getBucketName(array $bucket, TranslatorInterface $translator, string $locale): string
    {
        $lower = $bucket['lower'];
        $upper = $bucket['upper'];
        if (-1 == $lower) {
            return $translator->trans('labels.aging_current', [], 'general', $locale);
        } elseif ($upper) {
            return $translator->trans('labels.aging_between_days', [
                '%lowerDays%' => $lower,
                '%upperDays%' => $upper,
            ], 'general', $locale);
        }

        return $translator->trans('labels.aging_plus_days', [
            '%lowerDays%' => $lower,
        ], 'general', $locale);
    }

    /**
     * Gets the age bucket for a given age.
     */
    public function getBucketForAge(int $age): array
    {
        foreach ($this->buckets as $i => $bucket) {
            $lower = $bucket['lower'];
            $upper = $bucket['upper'];

            if (-1 == $lower) {
                if (-1 == $age) {
                    return $bucket;
                }
            } elseif (($age >= $lower || ($age < $lower && 0 == $i)) && (!$upper || $age <= $upper)) {
                return $bucket;
            }
        }

        return [];
    }

    /**
     * Gets the age since now for a date timestamp.
     *
     * The date provided should be the invoice date when date_column=date
     * and due date when date_column=due_date.
     */
    public function getAgeForTimestamp(?int $date): int
    {
        if ('due_date' == $this->dateColumn) {
            if (!$date || $date > time()) {
                return -1;
            }

            return (int) max(0, floor((time() - $date) / 86400));
        }

        if (!$date) {
            return 0;
        }

        return (int) max(0, floor((time() - $date) / 86400));
    }

    /**
     * Checks if an age falls within a bucket.
     */
    public function isInBucket(int $age, array $bucket): bool
    {
        if (-1 == $bucket['lower']) {
            return -1 == $age;
        }

        return $age >= $bucket['lower'] && (!$bucket['upper'] || $age <= $bucket['upper']);
    }

    /**
     * Gets the color for an aging bucket.
     */
    public function getColor(array $bucket): string
    {
        return $bucket['color'];
    }

    /**
     * Gets the color for an aging bucket.
     */
    private function generateColor(int $index, int $numBuckets): string
    {
        // severity is a value 1 (lowest) - 5 (highest)
        // this maps an arbitrary number of aging buckets (not always 5)
        // onto this severity range
        $severity = (int) ceil(($index + 1) * 5 / $numBuckets);

        return self::AGING_COLORS[$severity];
    }
}
