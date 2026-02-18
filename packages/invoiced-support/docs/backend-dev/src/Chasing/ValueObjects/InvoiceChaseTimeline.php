<?php

namespace App\Chasing\ValueObjects;

use Iterator;

/**
 * Immutable representation of an invoice chasing schedule's timeline.
 *
 * Each point (or segment) of the timeline contains a sorted list of dates
 * which represent which are when the invoice will be chased.
 */
class InvoiceChaseTimeline implements Iterator
{
    private int $index;

    /**
     * @param InvoiceChaseTimelineSegment[] $timeline
     */
    public function __construct(private array $timeline)
    {
        $this->index = 0;
    }

    public function current(): InvoiceChaseTimelineSegment
    {
        return $this->timeline[$this->index]->copy();
    }

    public function next(): void
    {
        ++$this->index;
    }

    public function key(): int
    {
        return $this->index;
    }

    public function valid(): bool
    {
        return isset($this->timeline[$this->index]);
    }

    public function rewind(): void
    {
        $this->index = 0;
    }

    public function size(): int
    {
        return count($this->timeline);
    }

    /**
     * Returns a map of step id -> timeline segment.
     *
     * NOTE: Value at key 'null' is unpredictable if there exists
     * more than one step w/ a null id.
     *
     * @return InvoiceChaseTimelineSegment[]
     */
    public function map(): array
    {
        $map = [];
        foreach ($this->timeline as $segment) {
            $map[$segment->getChaseStep()->getId()] = $segment->copy();
        }

        return $map;
    }
}
