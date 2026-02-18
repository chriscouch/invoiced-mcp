<?php

namespace App\Core\Utils;

class ObjectTypes
{
    /** @var string[] */
    public static array $nameCache = [
        'LegacyEmail' => 'email', // An override is needed for this because the enum name cannot translate to snake casing
    ];
}
