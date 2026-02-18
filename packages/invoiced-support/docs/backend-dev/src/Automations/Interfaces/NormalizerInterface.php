<?php

namespace App\Automations\Interfaces;

interface NormalizerInterface
{
    public function normalize(mixed $value): mixed;

    public static function getId(): string;
}
