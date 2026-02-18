<?php

namespace App\Entity\Forms;

use DateTime;
use DateTimeZone;
use Symfony\Component\Validator\Constraints as Assert;

class EmailLogSearch
{
    const DATE_FORMAT = 'Y-m-d H:i:s.v';

    #[Assert\NotBlank]
    private string $email;
    private ?DateTime $startTime = null;
    private ?DateTime $endTime = null;
    private int $numResults = 25;

    public function toString(): string
    {
        $parts = [];

        if ($this->email) {
            $parts['Email'] = $this->email;
        }

        if ($this->startTime) {
            $parts['Start Time'] = $this->startTime->format(self::DATE_FORMAT);
        }

        if ($this->endTime) {
            $parts['End Time'] = $this->endTime->format(self::DATE_FORMAT);
        }

        return join(', ', array_map(function ($v, $k) {
            return "$k: $v";
        }, $parts, array_keys($parts)));
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getNumResults(): int
    {
        return $this->numResults;
    }

    public function setNumResults(int $numResults): void
    {
        $this->numResults = $numResults;
    }

    public function getStartTime(): ?DateTime
    {
        return $this->startTime;
    }

    public function getStartTimeUtc(): ?DateTime
    {
        return $this->startTime ? $this->startTime->setTimezone(new DateTimeZone('UTC')) : null;
    }

    public function setStartTime(?DateTime $startTime): void
    {
        $this->startTime = $startTime;
    }

    public function getEndTime(): ?DateTime
    {
        return $this->endTime;
    }

    public function getEndTimeUtc(): ?DateTime
    {
        return $this->endTime ? $this->endTime->setTimezone(new DateTimeZone('UTC')) : null;
    }

    public function setEndTime(?DateTime $endTime): void
    {
        $this->endTime = $endTime;
    }
}
