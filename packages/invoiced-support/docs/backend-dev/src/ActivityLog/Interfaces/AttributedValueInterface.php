<?php

namespace App\ActivityLog\Interfaces;

use JsonSerializable;

interface AttributedValueInterface extends JsonSerializable
{
    public function __toString(): string;
}
