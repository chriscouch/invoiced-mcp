<?php

namespace App\Automations\ValueObjects;

use App\Automations\Enums\AutomationResult;

final class AutomationOutcome
{
    public function __construct(
        public readonly AutomationResult $result,
        public readonly ?string $errorMessage = null,
        public bool $terminate = false,
    ) {
    }
}
