<?php

namespace App\Integrations\Traits;

use App\Companies\Models\Company;
use App\Core\Utils\DebugContext;
use App\Integrations\Libs\CloudWatchHandler;
use App\Integrations\Libs\IntegrationLogProcessor;
use App\Integrations\Libs\LoggingHttpClient;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

trait IntegrationLogAwareTrait
{
    protected LoggingHttpClient $loggingHttpClient;

    protected function makeIntegrationLogger(string $channel, Company $company, CloudWatchLogsClient $client, DebugContext $debugContext): Logger
    {
        $logger = new Logger($channel);

        // log to cloudwatch when in a production environment
        if (!in_array($debugContext->getEnvironment(), ['dev', 'test'])) {
            $logger->pushHandler($this->getCloudWatchHandler($channel, $client, $company));
        } else {
            $logfile = dirname(dirname(dirname(__DIR__))).'/var/log/'.$channel.'-'.$company->id().'.log';
            $logger->pushHandler(new StreamHandler($logfile, Logger::DEBUG));
        }

        $processor = new IntegrationLogProcessor($company, $debugContext);
        $logger->pushProcessor($processor);

        return $logger;
    }

    protected function makeGuzzleLogger(string $channel, Company $company, CloudWatchLogsClient $client, DebugContext $debugContext): HandlerStack
    {
        $handlerStack = HandlerStack::create();
        $handlerStack->push(
            Middleware::log(
                $this->makeIntegrationLogger($channel, $company, $client, $debugContext),
                new MessageFormatter(MessageFormatter::DEBUG)
            )
        );

        return $handlerStack;
    }

    protected function makeSymfonyLogger(string $channel, Company $company, CloudWatchLogsClient $client, DebugContext $debugContext, HttpClientInterface $httpClient): LoggingHttpClient
    {
        $logger = $this->makeIntegrationLogger($channel, $company, $client, $debugContext);

        return new LoggingHttpClient($httpClient, $logger);
    }

    protected function getCloudWatchHandler(string $channel, CloudWatchLogsClient $client, Company $company, int $level = Logger::DEBUG): AbstractHandler
    {
        if (!$company->features->has('log_'.$channel)) {
            return new NullHandler();
        }

        $stream = (string) gethostname();

        return new CloudWatchHandler($client, '/invoiced/Integrations', $stream, 0, 10000, [], $level, true, false);
    }
}
