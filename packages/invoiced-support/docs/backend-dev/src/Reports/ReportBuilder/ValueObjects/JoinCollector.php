<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use RuntimeException;

final class JoinCollector
{
    /** @var JoinCondition[] */
    private array $joins = [];
    private array $alreadyJoined = [];
    private bool $finalized = false;

    public function __construct(string $object)
    {
        $this->alreadyJoined[] = $object;
    }

    public function add(JoinCondition $join): void
    {
        if ($this->finalized) {
            throw new RuntimeException('Join collector is already finalized');
        }

        // ensure each unique join is only added once
        $tableAlias = $join->joinTable->alias;
        if (in_array($tableAlias, $this->alreadyJoined)) {
            return;
        }

        $this->joins[] = $join;
        $this->alreadyJoined[] = $tableAlias;
    }

    public function finalize(): void
    {
        if ($this->finalized) {
            throw new RuntimeException('Join collector is already finalized');
        }

        $this->finalized = true;
    }

    public function all(): Joins
    {
        if (!$this->finalized) {
            throw new RuntimeException('Join collector is not finalized');
        }

        return new Joins($this->joins);
    }
}
