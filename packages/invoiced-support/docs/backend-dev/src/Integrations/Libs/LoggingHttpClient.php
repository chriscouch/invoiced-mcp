<?php

namespace App\Integrations\Libs;

use Monolog\Logger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class LoggingHttpClient implements HttpClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private Logger $logger,
    ) {
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $response = $this->httpClient->request($method, $url, $options);
        $content = $response->getContent(false);

        // Log the request
        $msg = ">>>>>>>>\n";
        if ($debug = $response->getInfo('debug')) {
            // Only get request headers from debug by skipping lines that start with `*` or `<`
            // Do not log sensitive lines like Authorization header
            $lines = explode("\n", $debug);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!str_starts_with($line, '* ') && !str_starts_with($line, '< ') && !str_starts_with($line, 'authorization') && $line) {
                    $msg .= "$line\n";
                }
            }
        }
        $msg .= "\n\n";

        if (isset($options['json'])) {
            $msg .= json_encode($options['json'])."\n";
        }

        // Log the response
        $msg .= "\n<<<<<<<<\n";
        foreach ($response->getInfo('response_headers') as $line) {
            $msg .= "$line\n";
        }
        $msg .= "\n\n";

        if ($content) {
            $msg .= $content;
        }

        $this->logger->info($msg);

        return $response;
    }

    public function stream(ResponseInterface|iterable $responses, float $timeout = null): ResponseStreamInterface
    {
        return $this->httpClient->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        $this->httpClient = $this->httpClient->withOptions($options);

        return $this;
    }
}
