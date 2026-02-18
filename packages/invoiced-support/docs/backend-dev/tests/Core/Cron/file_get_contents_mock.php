<?php

namespace App\Core\Cron;

use Mockery\LegacyMockInterface;

function file_get_contents(string $cmd): string
{
    return FileGetContentsMock::$functions->file_get_contents($cmd);
}

class FileGetContentsMock
{
    public static LegacyMockInterface $functions;
}
