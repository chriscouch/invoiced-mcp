<?php

namespace App\ActivityLog\ValueObjects;

use JsonSerializable;
use stdClass;

class EventData implements JsonSerializable
{
    public function __construct(
        public readonly object $object,
        public readonly ?object $previous = null,
        public readonly ?array $message = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'object' => $this->object,
            'previous' => $this->previous,
            'message' => $this->message,
        ];
    }

    public static function fromJson(string $json): self
    {
        $result = (object) json_decode($json);

        return new EventData($result->object ?? new stdClass(), $result->previous ?? null, $result->message ?? null);
    }
}
