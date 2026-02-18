<?php

namespace App\Core\Cron\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property string      $id
 * @property int|null    $last_ran
 * @property bool        $last_run_succeeded
 * @property string|null $last_run_output
 */
class CronJob extends Model
{
    protected static function getProperties(): array
    {
        return [
            'id' => new Property(
                required: true,
            ),
            'last_ran' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'last_run_succeeded' => new Property(
                type: Type::BOOLEAN,
            ),
            'last_run_output' => new Property(
                null: true,
            ),
        ];
    }
}
