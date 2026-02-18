<?php

namespace App\Reports\ReportBuilder\Interfaces;

use App\Reports\Enums\ColumnType;

interface ExpressionInterface
{
    /**
     * Gets the proposed column name of this expression.
     * This function will be used when a column name is
     * not provided by the user.
     *
     * A null name means that there is no proposed name
     * for this expression.
     */
    public function getName(): ?string;

    /**
     * Gets the data type of values returned by this expression.
     *
     * A null type means that there is no type for this expression.
     */
    public function getType(): ?ColumnType;

    /**
     * Generates a select alias name for this expression
     * in the select list. This does not have to be unique.
     */
    public function getSelectAlias(): string;
}
