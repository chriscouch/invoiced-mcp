<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedString;
use ICanBoogie\Inflector;

class ImportMessage extends BaseMessage
{
    protected function importFinished(): array
    {
        if (isset($this->object['count'])) {
            $n = $this->object['count'];
        } else {
            $n = array_value($this->object, 'num_imported');
        }

        $inflector = Inflector::get();
        $type = strtolower($inflector->humanize((string) array_value($this->object, 'type')));
        $typePlural = $inflector->pluralize($type);
        $message = $n.' ';
        $message .= 1 == $n ? "$type was" : "$typePlural were";
        $message .= ' imported';

        return [
            new AttributedString($message),
        ];
    }
}
