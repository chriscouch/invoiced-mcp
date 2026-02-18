<?php

namespace App\Core\Orm\Validation;

use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;

/**
 * Checks if a value is unique for a property.
 *
 * Options:
 * - column: specifies which column must be unique (required)
 */
class Unique implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        $name = $options['column'];
        if (!$model->dirty($name, true)) {
            return true;
        }

        return 0 == $model::query()->where([$name => $value])->count();
    }
}
