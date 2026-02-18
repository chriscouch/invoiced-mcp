<?php

namespace App\Controller;

use App\Entity\CustomerAdmin\User;
use App\Entity\Forms\IntegrationLogSearch;
use App\Form\SearchIntegrationLogsType;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\CloudWatchLogs\Exception\CloudWatchLogsException;
use Aws\Sdk;
use Carbon\CarbonImmutable;
use DOMDocument;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Throwable;

class IntegrationLogViewerController extends AbstractController
{
    #[Route(path: '/admin/integration_logs', name: 'integration_log_viewer')]
    public function searchForm(Request $request, LoggerInterface $logger, string $apiLogEnvironment, Sdk $sdk, Security $security): Response
    {
        // use the signed in user's time zone
        /** @var User $user */
        $user = $security->getUser();
        date_default_timezone_set($user->getTimeZone());
        $client = $this->getClient($apiLogEnvironment, $sdk);
        $form = $this->getForm($request, $apiLogEnvironment);
        if ($form->isSubmitted() && $form->isValid()) {
            $filter = $form->getData();

            try {
                $resultSet = $this->searchLogs($client, $filter);
            } catch (CloudWatchLogsException $e) {
                $logger->error('DynamoDB query failed', ['exception' => $e]);

                return $this->render('integration_log_viewer/new_search.html.twig', [
                    'form' => $form->createView(),
                    'error' => $e->getMessage(),
                ]);
            }

            return $this->render('integration_log_viewer/view_results.html.twig', [
                'results' => $resultSet,
                'filter' => $filter->toString(),
            ]);
        }

        return $this->render('integration_log_viewer/new_search.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @throws CloudWatchLogsException
     */
    private function searchLogs(CloudWatchLogsClient $client, IntegrationLogSearch $filter): array
    {
        $filterPattern = [
            '$.meta.tenant_id = '.$filter->getTenant(),
            '$.meta.channel = "'.$this->getSearchChannel($filter->getChannel()).'"',
        ];
        if ($searchTerm = $filter->getSearchTerm()) {
            $filterPattern[] = '$.message = "*'.addslashes($searchTerm).'*"';
        }
        // TODO: this is not inclusive of other levels
        if ($minLevel = $filter->getMinLevel()) {
            $filterPattern[] = '$.level = "'.$minLevel.'"';
        }
        if ($correlationId = $filter->getCorrelationId()) {
            $filterPattern[] = '$.meta.correlation_id = "'.$correlationId.'"';
        }
        $filterPattern = '{ '.implode(' && ', $filterPattern).' }';

        $params = [
            'logGroupName' => $this->getLogGroupName($filter->getChannel()),
            'limit' => $filter->getNumResults(),
            'filterPattern' => $filterPattern,
            'interleaved' => true,
            'startTime' => $filter->getStartTime()->getTimestamp() * 1000,
            'endTime' => $filter->getEndTime()->getTimestamp() * 1000,
        ];

        $results = [];
        $hasMore = true;
        while ($hasMore && count($results) < $filter->getNumResults()) {
            $result = $client->filterLogEvents($params);
            $results = array_merge($results, $this->parseEvents($result['events']));
            $hasMore = $result['nextToken'] ?? false;
            $params['nextToken'] = $hasMore;
        }

        return $results;
    }

    private function getClient(string $environment, Sdk $sdk): CloudWatchLogsClient
    {
        $region = match ($environment) {
            'staging' => 'us-west-2',
            'sandbox' => 'us-west-1',
            'production', 'dev' => 'us-east-2',
            default => '',
        };

        return $sdk->createCloudWatchLogs([
            'region' => $region,
        ]);
    }

    private function getLogGroupName(string $channel): string
    {
        return match ($channel) {
            'quickbooks_desktop', 'syncserver_http' => '/ecs/AccountingSync',
            default => '/invoiced/Integrations',
        };
    }

    private function getSearchChannel(string $channel): string
    {
        return match ($channel) {
            'syncserver_http' => 'http',
            'quickbooks_online' => 'quickbooks',
            default => $channel,
        };
    }

    private function getForm(Request $request, string $apiLogEnvironment): FormInterface
    {
        $filter = new IntegrationLogSearch();

        $filter->setEnvironment($apiLogEnvironment);

        if ($tenantId = $request->query->get('tenantId')) {
            $filter->setTenant((int) $tenantId);
        }

        $form = $this->createForm(SearchIntegrationLogsType::class, $filter);

        $form->handleRequest($request);

        return $form;
    }

    private function parseEvents(array $events): array
    {
        $results = [];
        foreach ($events as $event) {
            // Parse the event JSON
            $parsed = json_decode($event['message'], true);

            // Ignoring events that are not JSON
            if (!is_array($parsed)) {
                continue;
            }

            $message = trim($parsed['message'] ?? '');

            $results[] = [
                'level' => $parsed['level'] ?? 'DEBUG',
                'message' => $this->prettyPrint($message),
                'timestamp' => CarbonImmutable::createFromTimestampMs($event['timestamp']),
                'meta' => $parsed['meta'],
            ];
        }

        return $results;
    }

    private function prettyPrint(string $input): string
    {
        // Check if this is an HTTP request / response
        if (str_contains($input, '>>>>>>>>') && str_contains($input, '<<<<<<<<')) {
            return implode("\n", array_map(
                fn ($input2) => $this->prettyPrint($input2),
                $this->parseHttpRequest($input))
            );
        }

        // Check if there is XML that can be pretty printed
        $pos = strpos($input, '<?xml');
        if (false !== $pos) {
            $substr = substr($input, $pos);

            return substr($input, 0, $pos).$this->prettyPrintXml($substr);
        }

        // Check if there is JSON that can be pretty printed
        $pos = strpos($input, '{');
        if (false !== $pos) {
            $substr = substr($input, $pos);

            return substr($input, 0, $pos).$this->prettyPrintJson($substr);
        }

        $pos = strpos($input, '[');
        if (false !== $pos) {
            $substr = substr($input, $pos);

            return substr($input, 0, $pos).$this->prettyPrintJson($substr);
        }

        return $input;
    }

    private function parseHttpRequest(string $input): array
    {
        $pos1 = (int) strpos($input, '>>>>>>>>');
        $pos2 = (int) strpos($input, '<<<<<<<<');
        $pos3 = (int) strpos($input, '--------');

        $request = substr($input, $pos1, $pos2 - $pos1);
        $response = substr($input, $pos2, $pos3 - $pos2);
        // third part intentionally ignored (.e.g. -------- NULL)

        return [$request, $response];
    }

    private function prettyPrintJson(string $input): string
    {
        $json = json_decode($input);
        if (!$json) {
            return $input;
        }

        return (string) json_encode($json, JSON_PRETTY_PRINT);
    }

    private function prettyPrintXml(string $input): string
    {
        try {
            $dom = new DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            if (@$dom->loadXML($input) && $result = $dom->saveXML()) {
                // Parse QuickBooks XML documents within an XML element that are
                // encoded as a string within these elements:
                //   <sendRequestXMLResult></sendRequestXMLResult>
                //   <response></response>
                //   <strHCPResponse></strHCPResponse>
                if (preg_match("/[\S\s]+<(?:(?:response|sendRequestXMLResult|strHCPResponse))>([\S\s]+)<\/(?:(?:response|sendRequestXMLResult|strHCPResponse))>[\S\s]+/", $result, $matches)) {
                    $subXml = htmlspecialchars_decode($matches[1]);
                    $subXml = "\n".trim($this->prettyPrintXml($subXml))."\n";
                    $result = str_replace($matches[1], $subXml, $result);
                }

                return $result;
            }
        } catch (Throwable) {
            // do nothing
        }

        return $input;
    }
}
