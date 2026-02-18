<?php

namespace App\Integrations\Libs;

use Monolog\Formatter\JsonFormatter;

class CloudWatchFormatter extends JsonFormatter
{
    public function __construct()
    {
        parent::__construct(self::BATCH_MODE_NEWLINES, false);
    }

    public function format(array $record)
    {
        return parent::format([
            'message' => $record['message'],
            'level' => $record['level_name'],
            'meta' => $record['context'],
        ]);
    }
}
