<?php

namespace App\Chasing\InvoiceChasing;

use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Chasing\ValueObjects\InvoiceChaseSchedule;
use App\Chasing\ValueObjects\InvoiceChaseStep;
use App\Core\Utils\RandomString;
use App\Sending\Models\ScheduledSend;
use InvalidArgumentException;

/**
 * Manager of invoice chase schedule step id generation.
 */
class InvoiceChaseScheduleInspector
{
    const ID_LENGTH = 32;

    /**
     * Adds ids to new steps in the schedule and refreshes ids of existing
     * steps that have been modified.
     *
     * @throws InvalidArgumentException if a step that has been attempted is deleted or modified
     *
     * @return InvoiceChaseSchedule new instance
     */
    public static function inspect(InvoiceDelivery $delivery, InvoiceChaseSchedule $oldSchedule, InvoiceChaseSchedule $newSchedule): InvoiceChaseSchedule
    {
        $oldScheduleMap = $oldSchedule->map();
        $newScheduleMap = $newSchedule->map();

        $processedSchedule = [];

        // look for step removals
        foreach ($oldSchedule as $step) {
            if (isset($newScheduleMap[$step->getId()])) {
                continue; // not removed from schedule
            }

            // removed from schedule; check if already sent
            if (!self::canModify($delivery, $step)) {
                throw new InvalidArgumentException('Chase step cannot be deleted because it has already been completed.');
            }
        }

        // look for modifications and additions
        foreach ($newSchedule as $step) {
            if ($step->getId() && isset($oldScheduleMap[$step->getId()])) {
                // check if step has been modified
                $oldStep = $oldScheduleMap[$step->getId()];
                if (!self::isChaseStepModified($oldStep, $step)) {
                    $processedSchedule[] = $step;
                    continue;
                }

                // step has been modified; check if already sent
                if (!self::canModify($delivery, $step)) {
                    throw new InvalidArgumentException('Chase step cannot be modified because it has already been completed.');
                }

                $processedSchedule[] = $step;
            } else {
                // step has been added
                $processedSchedule[] = self::setChaseStepId($step);
            }
        }

        return new InvoiceChaseSchedule($processedSchedule);
    }

    /**
     * Sets a new id on the provided invoice chase step.
     */
    private static function setChaseStepId(InvoiceChaseStep $step): InvoiceChaseStep
    {
        return new InvoiceChaseStep($step->getTrigger(), $step->getOptions(), self::generateChaseStepId());
    }

    /**
     * Determines if a step has been modified.
     */
    private static function isChaseStepModified(InvoiceChaseStep $oldStep, InvoiceChaseStep $newStep): bool
    {
        return $oldStep->getId() === $newStep->getId() &&
            ($oldStep->getTrigger() !== $newStep->getTrigger() || $oldStep->getOptions() !== $newStep->getOptions());
    }

    /**
     * Checks if an invoice chase step can be modified.
     */
    private static function canModify(InvoiceDelivery $delivery, InvoiceChaseStep $step): bool
    {
        $attempted = ScheduledSend::where('invoice_id', $delivery->invoice->id())
            ->where('reference', InvoiceDelivery::getSendReference($delivery, $step))
            ->where('(sent = TRUE or failed = TRUE or skipped = TRUE)')
            ->count();
        $totalSends = ScheduledSend::where('invoice_id', $delivery->invoice->id())
            ->where('reference', InvoiceDelivery::getSendReference($delivery, $step))
            ->count();

        return 0 === $totalSends || $attempted != $totalSends;
    }

    /**
     * Generates an id for an invoice chase step.
     */
    public static function generateChaseStepId(): string
    {
        return RandomString::generate(self::ID_LENGTH, RandomString::CHAR_ALNUM);
    }
}
