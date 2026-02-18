<?php

namespace App\Core\Utils;

use App\Integrations\Libs\CloudWatchHandler;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use RuntimeException;

class LoggerFactory
{
    private const CLOUDWATCH_LOG_GROUPS = [
        'fraud' => '/invoiced/Fraud',
    ];

    public function __construct(
        private string $environment,
        private CloudWatchLogsClient $cloudWatchLogsClient,
    ) {
    }

    public function get(string $name): LoggerInterface
    {
        $logGroupName = self::CLOUDWATCH_LOG_GROUPS[$name] ?? null;
        if (!$logGroupName) {
            throw new RuntimeException('Log group not recognized: '.$name);
        }

        $logger = new Logger($name);
        if (!in_array($this->environment, ['dev', 'test'])) {
            $stream = (string) gethostname();
            $handler = new CloudWatchHandler($this->cloudWatchLogsClient, $logGroupName, $stream, 0, 10000, [], Logger::DEBUG, true, false);
            $formatter = new LineFormatter('%message% %context% %extra%', null, true, true);
            $handler->setFormatter($formatter);
            $logger->pushHandler($handler);

            return $logger;
        }

        $logfile = dirname(dirname(dirname(__DIR__))).'/var/log/'.$name.'.log';
        $logger->pushHandler(new StreamHandler($logfile, Logger::DEBUG));

        return $logger;
    }
}
