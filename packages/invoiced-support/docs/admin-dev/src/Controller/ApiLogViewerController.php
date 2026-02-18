<?php

namespace App\Controller;

use App\Entity\CustomerAdmin\User;
use App\Entity\Forms\ApiLogSearch;
use App\Entity\Forms\PaymentLogSearch;
use App\Entity\Invoiced\User as InvoicedUser;
use App\Form\SearchApiLogsType;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Aws\PsrCacheAdapter;
use DateTime;
use DateTimeZone;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class ApiLogViewerController extends AbstractController
{
    const LOG_DATE_FORMAT = 'Y-m-d H:i:s.v';

    public function getDynamoDb(string $environment, PsrCacheAdapter $cache): DynamoDbClient
    {
        if ('staging' == $environment) {
            return new DynamoDbClient([
                'region' => 'us-west-2',
                'version' => 'latest',
                'credentials' => $cache,
            ]);
        } elseif ('sandbox' == $environment) {
            return new DynamoDbClient([
                'region' => 'us-west-1',
                'version' => 'latest',
                'credentials' => $cache,
            ]);
        }

        return new DynamoDbClient([
            'region' => 'us-east-2',
            'version' => 'latest',
        ]);
    }

    public function getForm(Request $request, string $apiLogEnvironment): FormInterface
    {
        $filter = new ApiLogSearch();

        $filter->setEnvironment($apiLogEnvironment);

        if ($tenantId = $request->query->get('tenantId')) {
            $filter->setTenant((int) $tenantId);
        }

        $form = $this->createForm(SearchApiLogsType::class, $filter);

        $form->handleRequest($request);

        return $form;
    }

    #[Route(path: '/admin/api_logs', name: 'api_log_viewer')]
    public function searchForm(Request $request, LoggerInterface $logger, string $apiLogEnvironment, PsrCacheAdapter $cache, Security $security, AdminUrlGenerator $adminUrlGenerator): Response
    {
        // use the signed in user's time zone
        /** @var User $user */
        $user = $security->getUser();
        date_default_timezone_set($user->getTimeZone());
        $dynamodb = $this->getDynamoDb($apiLogEnvironment, $cache);
        $form = $this->getForm($request, $apiLogEnvironment);
        if ($form->isSubmitted() && $form->isValid()) {
            $filter = $form->getData();

            try {
                $resultSet = $this->searchLogs($dynamodb, $filter);
            } catch (DynamoDbException $e) {
                $logger->error('DynamoDB query failed', ['exception' => $e]);

                return $this->render('api_log_viewer/new_search.html.twig', [
                    'form' => $form->createView(),
                    'error' => $e->getMessage(),
                ]);
            }

            if (1 == count($resultSet)) {
                $item = $resultSet[0];
                $url = $adminUrlGenerator->setRoute('view_api_request', ['tenantId' => $item['tenantId'], 'requestId' => $item['request_id']])
                    ->generateUrl();

                return $this->redirect($url);
            }

            return $this->render('api_log_viewer/search_results.html.twig', [
                'results' => $resultSet,
                'count' => count($resultSet),
                'filter' => $filter->toString(),
            ]);
        }

        return $this->render('api_log_viewer/new_search.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/admin/request_logs/{tenantId}/{requestId}', name: 'view_api_request')]
    public function viewRequest(Request $request, string $apiLogEnvironment, string $tenantId, string $requestId, LoggerInterface $logger, PsrCacheAdapter $cache, Security $security): Response
    {
        // use the signed in user's time zone
        /** @var User $user */
        $user = $security->getUser();
        date_default_timezone_set($user->getTimeZone());
        $dynamodb = $this->getDynamoDb($apiLogEnvironment, $cache);
        $id = $apiLogEnvironment.':'.$tenantId;
        $params = [
            'TableName' => 'InvoicedApiLogs',
            'IndexName' => 'RequestIdIndex',
            'Select' => 'ALL_ATTRIBUTES',
            'KeyConditions' => [
                'request_id' => [
                    'AttributeValueList' => [
                        ['S' => $requestId],
                    ],
                    'ComparisonOperator' => 'EQ',
                ],
            ],
            'FilterExpression' => 'id = :id',
            'ExpressionAttributeValues' => [
                ':id' => ['S' => $id],
            ],
            'ScanIndexForward' => false,
            'TotalSegments' => 5,
        ];
        $lastKey = null;
        $hasMore = true;
        $apiRequest = null;
        $marshaler = new Marshaler();
        while ($hasMore) {
            if ($lastKey) {
                $params['ExclusiveStartKey'] = $lastKey;
            }

            try {
                $result = $dynamodb->query($params);
            } catch (DynamoDbException $e) {
                $logger->error('DynamoDB query failed', ['exception' => $e]);
                $form = $this->getForm($request, $apiLogEnvironment);

                return $this->render('api_log_viewer/new_search.html.twig', [
                    'form' => $form->createView(),
                    'error' => $e->getMessage(),
                ]);
            }

            if (count($result['Items']) > 0) {
                $apiRequest = (array) $marshaler->unmarshalItem($result['Items'][0]);
                $hasMore = false;
            } else {
                $lastKey = $result['LastEvaluatedKey'] ?? null;
                $hasMore = isset($result['LastEvaluatedKey']);
            }
        }
        if (!$apiRequest) {
            throw new NotFoundHttpException("Could not find request: $id / $requestId");
        }
        $requestBody = null;
        if (isset($apiRequest['request_body'])) {
            $requestBody = (string) gzinflate(base64_decode($apiRequest['request_body']));

            $decoded = json_decode($requestBody);
            if ($decoded) {
                $requestBody = json_encode($decoded, JSON_PRETTY_PRINT);
            }
        }
        $responseBody = null;
        if (isset($apiRequest['response'])) {
            $responseBody = (string) gzinflate(base64_decode($apiRequest['response']));

            $decoded = json_decode($responseBody);
            if ($decoded) {
                $responseBody = json_encode($decoded, JSON_PRETTY_PRINT);
            }
        }
        $parts = explode(':', $id);
        $environment = $parts[0];
        $tenantId = $parts[1];
        $user = null;
        if (isset($apiRequest['user'])) {
            $user = $this->getDoctrine()
                ->getManager('Invoiced_ORM')
                ->getRepository(InvoicedUser::class)
                ->find($apiRequest['user']);
        }

        return $this->render('api_log_viewer/view_request.html.twig', [
            'requestId' => $requestId,
            'correlationId' => $apiRequest['correlation_id'] ?? null,
            'tenantId' => $tenantId,
            'environment' => $environment,
            'method' => $apiRequest['method'],
            'route_name' => $apiRequest['route_name'],
            'endpoint' => $apiRequest['endpoint'],
            'timestamp' => $this->toUnixTimestamp($apiRequest['timestamp']),
            'ip' => $apiRequest['ip'],
            'statusCode' => $apiRequest['status_code'],
            'userAgent' => $apiRequest['user_agent'],
            'queryParams' => $apiRequest['query_params'] ?? null,
            'requestHeaders' => $apiRequest['request_headers'] ?? null,
            'responseHeaders' => $apiRequest['response_headers'] ?? null,
            'responseTime' => round(($apiRequest['response_time'] ?? 0) / 1000.0, 3),
            'requestBody' => $requestBody,
            'responseBody' => $responseBody,
            'apiKey' => $apiRequest['api_key'] ?? null,
            'user' => $user,
        ]);
    }

    /**
     * @throws DynamoDbException
     */
    private function searchLogs(DynamoDbClient $dynamodb, ApiLogSearch $filter): array
    {
        $params = [
            'TableName' => 'InvoicedApiLogs',
            'ProjectionExpression' => 'id, request_id, correlation_id, #timestamp, #method, endpoint, route_name, response_time, status_code',
            'ExpressionAttributeNames' => [
                '#timestamp' => 'timestamp',
                '#method' => 'method',
            ],
            'ScanIndexForward' => false,
            'TotalSegments' => 3,
        ];

        $keyConditionExpression = [];
        $filterExpression = [];
        $filterAttributeValues = [];

        if ($requestId = $filter->getRequestId()) {
            // When filtering by request ID use a dedicated index for faster results
            $params['IndexName'] = 'RequestIdIndex';
            $keyConditionExpression[] = 'request_id = :request_id';
            $filterAttributeValues[':request_id'] = ['S' => $requestId];
            $filterExpression[] = 'id = :id';
            $filterAttributeValues[':id'] = ['S' => $filter->getId()];
        } else {
            $keyConditionExpression[] = 'id = :id';
            $filterAttributeValues[':id'] = ['S' => $filter->getId()];
        }

        // Time ranges are in viewer's time zone. These
        // MUST be converted to UTC before querying DynamoDB.
        $startTime = $filter->getStartTimeUtc();
        $endTime = $filter->getEndTimeUtc();
        if ($startTime && $endTime) {
            $keyConditionExpression[] = '#timestamp BETWEEN :start_time AND :end_time';
            $filterAttributeValues[':start_time'] = ['S' => $startTime->format(PaymentLogSearch::DATE_FORMAT)];
            $filterAttributeValues[':end_time'] = ['S' => $endTime->format(PaymentLogSearch::DATE_FORMAT)];
        } elseif ($startTime) {
            $keyConditionExpression[] = '#timestamp >= :start_time';
            $filterAttributeValues[':start_time'] = ['S' => $startTime->format(PaymentLogSearch::DATE_FORMAT)];
        } elseif ($endTime) {
            $keyConditionExpression[] = '#timestamp <= :end_time';
            $filterAttributeValues[':end_time'] = ['S' => $endTime->format(PaymentLogSearch::DATE_FORMAT)];
        }

        $params['KeyConditionExpression'] = join(' and ', $keyConditionExpression);

        if ($correlationId = $filter->getCorrelationId()) {
            $filterExpression[] = 'correlation_id = :correlation_id';
            $filterAttributeValues[':correlation_id'] = ['S' => $correlationId];
        }

        if ($method = $filter->getMethod()) {
            $filterExpression[] = '#method = :method';
            $filterAttributeValues[':method'] = ['S' => $method];
        }

        if ($endpoint = $filter->getEndpoint()) {
            $filterExpression[] = 'endpoint = :endpoint';
            $filterAttributeValues[':endpoint'] = ['S' => $endpoint];
        }

        if ($routeName = $filter->getRouteName()) {
            $filterExpression[] = 'route_name = :route_name';
            $filterAttributeValues[':route_name'] = ['S' => $routeName];
        }

        if ($statusCode = $filter->getStatusCode()) {
            $filterExpression[] = 'status_code = :status_code';
            $filterAttributeValues[':status_code'] = ['N' => (string) $statusCode];
        }

        if ($userAgent = $filter->getUserAgent()) {
            $filterExpression[] = 'user_agent = :user_agent';
            $filterAttributeValues[':user_agent'] = ['S' => (string) $userAgent];
        }

        if (count($filterExpression) > 0) {
            $params['FilterExpression'] = join(' and ', $filterExpression);
        }

        $params['ExpressionAttributeValues'] = $filterAttributeValues;

        $lastKey = null;
        $hasMore = true;
        $resultSet = [];
        $marshaler = new Marshaler();

        while (count($resultSet) < $filter->getNumResults() && $hasMore) {
            if ($lastKey) {
                $params['ExclusiveStartKey'] = $lastKey;
            }

            $result = $dynamodb->query($params);

            foreach ($result['Items'] as $item) {
                $item = (array) $marshaler->unmarshalItem($item);
                [, $tenantId] = explode(':', $item['id']);
                $item['tenantId'] = $tenantId;
                $item['timestamp'] = $this->toUnixTimestamp($item['timestamp']);
                $resultSet[] = $item;
            }
            $lastKey = $result['LastEvaluatedKey'] ?? null;
            $hasMore = isset($result['LastEvaluatedKey']);
        }

        return array_slice($resultSet, 0, $filter->getNumResults());
    }

    /**
     * Converts the API log timestamp format to a UNIX timestamp.
     */
    private function toUnixTimestamp(string $timestamp): int
    {
        $date = DateTime::createFromFormat(self::LOG_DATE_FORMAT, $timestamp, new DateTimeZone('UTC'));
        if (!$date) {
            return 0;
        }

        return (int) $date->format('U');
    }
}
