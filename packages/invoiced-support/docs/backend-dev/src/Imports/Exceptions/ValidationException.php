<?php

namespace App\Imports\Exceptions;

class ValidationException extends \Exception
{
    private mixed $record = null;
    private ?int $lineNumber = null;

    /**
     * @return $this
     */
    public function setRecord(mixed $record): self
    {
        $this->record = $record;

        return $this;
    }

    public function getRecord(): mixed
    {
        return $this->record;
    }

    /**
     * @return $this
     */
    public function setLineNumber(int $n): self
    {
        $this->lineNumber = $n;

        return $this;
    }

    public function getLineNumber(): ?int
    {
        return $this->lineNumber;
    }
}
