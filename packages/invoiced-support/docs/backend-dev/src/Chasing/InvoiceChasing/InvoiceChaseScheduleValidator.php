<?php

namespace App\Chasing\InvoiceChasing;

use App\Chasing\Models\InvoiceChasingCadence;
use InvalidArgumentException;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Validator used to validate the format of an invoice chasing schedule.
 */
class InvoiceChaseScheduleValidator
{
    const STEP_LIMIT = 100;

    /**
     * Validates a chasing schedule format.
     *
     * @throws InvalidArgumentException on invalid schedule
     */
    public static function validate(array $schedule): void
    {
        // enforce step limit
        if (count($schedule) > self::STEP_LIMIT) {
            throw new InvalidArgumentException('Chasing schedules cannot exceed '.self::STEP_LIMIT.' steps.');
        }

        // Maps trigger to an array of used options.
        // Used to find steps that have the same trigger
        // and options.
        $triggerMap = [];
        $stepResolver = self::getStepResolver();
        foreach ($schedule as $step) {
            if (!is_array($step)) {
                throw new InvalidArgumentException("Malformed schedule data. Expected '$step' to be an array");
            }

            try {
                $stepResolver->resolve($step);
                $optionsResolver = self::getOptionsResolver($step['trigger']);
                $optionsResolver->resolve($step['options']);

                // check if duplicate
                if (self::checkDuplicate($triggerMap, $step)) {
                    throw new InvalidArgumentException('A duplicate step exists in the schedule');
                }

                // ensure at least one send channel is selected
                self::enforceSendChannel($step);
            } catch (ExceptionInterface $e) {
                throw new InvalidArgumentException($e->getMessage());
            }
        }
    }

    /**
     * Returns an OptionsResolver for validating steps.
     */
    private static function getStepResolver(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined(['id']);
        $resolver->setRequired(['trigger', 'options']);
        $resolver->setAllowedTypes('id', ['string', 'null']);
        $resolver->setAllowedTypes('options', ['array']);
        $resolver->setAllowedTypes('trigger', ['numeric']);
        $resolver->setAllowedValues('trigger', [
            InvoiceChasingCadence::ON_ISSUE,
            InvoiceChasingCadence::BEFORE_DUE,
            InvoiceChasingCadence::AFTER_DUE,
            InvoiceChasingCadence::REPEATER,
            InvoiceChasingCadence::ABSOLUTE,
            InvoiceChasingCadence::AFTER_ISSUE,
        ]);

        return $resolver;
    }

    /**
     * Returns an OptionsResolver for validating a step's options.
     */
    private static function getOptionsResolver(int $trigger): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined(['role']);
        $resolver->setRequired([
            'hour', // the hour at which to send the invoice
            'email', // whether or not to send the invoice via email
            'sms', // whether or not to send the invoice via sms
            'letter', // whether or not to send the invoice via letter
        ]);
        $resolver->setAllowedTypes('role', ['numeric', 'null']);
        $resolver->setAllowedTypes('hour', ['numeric']);
        $resolver->setAllowedTypes('email', ['bool']);
        $resolver->setAllowedTypes('sms', ['bool']);
        $resolver->setAllowedTypes('letter', ['bool']);
        $resolver->setNormalizer('hour', self::getNumberValueNormalizer('hour', 0));

        if (InvoiceChasingCadence::ABSOLUTE === $trigger) {
            $resolver->setRequired(['date']); // absolute date to send the invoice
            $resolver->setAllowedTypes('date', ['string']);
        } elseif (in_array($trigger, [
                InvoiceChasingCadence::BEFORE_DUE,
                InvoiceChasingCadence::AFTER_DUE,
                InvoiceChasingCadence::AFTER_ISSUE,
            ])) {
            $resolver->setRequired(['days']); // days before, after or repeating
            $resolver->setAllowedTypes('days', ['numeric']);
            $resolver->setNormalizer('days', self::getNumberValueNormalizer('days', 0));
        } elseif (InvoiceChasingCadence::REPEATER === $trigger) {
            $resolver->setRequired(['days', 'repeats']);
            $resolver->setAllowedTypes('days', ['numeric']);
            $resolver->setNormalizer('days', self::getNumberValueNormalizer('days', 1));
            $resolver->setAllowedTypes('repeats', ['numeric']);
            $resolver->setNormalizer('repeats', self::getNumberValueNormalizer('repeats', 1, 10));
        }

        return $resolver;
    }

    /**
     * Enforces that at least one send channel is selected
     * for a chase step configuration.
     *
     * @throws InvalidArgumentException
     */
    private static function enforceSendChannel(array $step): void
    {
        $channels = ['email', 'sms', 'letter'];
        $options = $step['options'];
        foreach ($channels as $channel) {
            $value = $options[$channel] ?? false;
            if ($value) {
                return;
            }
        }

        throw new InvalidArgumentException('Chase step is missing a send channel');
    }

    /**
     * Checks if a step is a duplicate based on the provided map.
     */
    private static function checkDuplicate(array &$map, array $step): bool
    {
        $trigger = $step['trigger'];
        $usedOptions = $map[$trigger] ?? [];

        // A subset of the step options which determines whether or not
        // steps are copies of each other. I.e. No two steps w/ the same trigger
        // should have this identical subset of step options.
        $stepValues = [
            'hour' => $step['options']['hour'],
            'role' => $step['options']['role'] ?? null,
        ];
        if (InvoiceChasingCadence::ABSOLUTE == $trigger) {
            $stepValues['date'] = $step['options']['date'];
        } elseif (InvoiceChasingCadence::REPEATER == $trigger) {
            // ensures only one repeater step can be used
            $stepValues = [];
        } elseif (in_array($trigger, [
            InvoiceChasingCadence::BEFORE_DUE,
            InvoiceChasingCadence::AFTER_DUE,
            InvoiceChasingCadence::AFTER_ISSUE,
        ])) {
            $stepValues['days'] = $step['options']['days'];
        }

        $isDuplicate = in_array($stepValues, $usedOptions);
        $map[$trigger][] = $stepValues;

        return $isDuplicate;
    }

    /**
     * Returns a normalizer for the integer options which should be greater than some value.
     */
    private static function getNumberValueNormalizer(string $key, int $minValue, ?int $maxValue = null): \Closure
    {
        return function (Options $options, $value) use ($key, $minValue, $maxValue) {
            if ($value < $minValue) {
                throw new InvalidOptionsException('Value "'.$key.'" must be greater than or equal to '.$minValue.'.');
            } elseif (null !== $maxValue && $value > $maxValue) {
                throw new InvalidOptionsException('Value "'.$key.'" must be less than or equal to '.$maxValue.'.');
            }

            return $value;
        };
    }
}
