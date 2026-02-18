<?php

namespace App\Core\ListQueryBuilders;

interface ListQueryBuilderInterface
{
    /**
     * @return class-string
     */
    public static function getClassString(): string;

    public function initialize(): void;
}
