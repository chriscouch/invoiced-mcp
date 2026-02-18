<?php

namespace App\Controller;

use App\Entity\CustomerAdmin\User;
use App\Entity\Forms\PaymentLogSearch;
use App\Form\SearchPaymentLogsType;
use App\Utilities\HttpUtility;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
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

class PaymentLogViewerController extends AbstractController
{
    const LOG_DATE_FORMAT = 'Y-m-d H:i:s.v';
    private HttpUtility $httpUtility;

    public function __construct(HttpUtility $utility)
    {
        $this->httpUtility = $utility;
    }

    private function getForm(Request $request, string $paymentApplicationId): FormInterface
    {
        $filter = new PaymentLogSearch();
        $filter->setApplicationId($paymentApplicationId);

        if ($tenantId = $request->query->get('tenantId')) {
            $filter->setTenant((string) $tenantId);
        }

        $form = $this->createForm(SearchPaymentLogsType::class, $filter);

        $form->handleRequest($request);

        return $form;
    }

    #[Route(path: '/admin/payment_logs', name: 'payment_log_viewer')]
    public function searchForm(Request $request, DynamoDbClient $dynamodb, LoggerInterface $logger, string $paymentApplicationId, Security $security, AdminUrlGenerator $adminUrlGenerator): Response
    {
        // use the signed in user's time zone
        /** @var User $user */
        $user = $security->getUser();
        date_default_timezone_set($user->getTimeZone());
        $form = $this->getForm($request, $paymentApplicationId);
        if ($form->isSubmitted() && $form->isValid()) {
            $filter = $form->getData();

            try {
                $resultSet = $this->searchLogs($dynamodb, $filter, $paymentApplicationId);
            } catch (DynamoDbException $e) {
                $logger->error('DynamoDB query failed', ['exception' => $e]);

                return $this->render('payment_log_viewer/new_search.html.twig', [
                    'form' => $form->createView(),
                    'error' => $e->getMessage(),
                ]);
            }

            if (1 == count($resultSet)) {
                $url = $adminUrlGenerator->setRoute('view_payment_request', ['requestId' => $resultSet[0]['request_id']])
                    ->generateUrl();

                return $this->redirect($url);
            }

            return $this->render('payment_log_viewer/search_results.html.twig', [
                'results' => $resultSet,
                'count' => count($resultSet),
                'filter' => $filter->toString(),
            ]);
        }

        return $this->render('payment_log_viewer/new_search.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/admin/payment_logs/{requestId}', name: 'view_payment_request')]
    public function viewRequest(Request $request, string $paymentApplicationId, string $requestId, DynamoDbClient $dynamodb, LoggerInterface $logger, Security $security): Response
    {
        // use the signed in user's time zone
        /** @var User $user */
        $user = $security->getUser();
        date_default_timezone_set($user->getTimeZone());
        $params = [
            'TableName' => 'InvoicedPaymentLogs',
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
            'FilterExpression' => 'application_id = :applicationId',
            'ExpressionAttributeValues' => [
                ':applicationId' => ['S' => $paymentApplicationId],
            ],
            'ScanIndexForward' => false,
            'TotalSegments' => 3,
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
                $form = $this->getForm($request, $paymentApplicationId);

                return $this->render('payment_log_viewer/new_search.html.twig', [
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
            throw new NotFoundHttpException("Could not find request: $paymentApplicationId / $requestId");
        }
        $requestBody = null;
        if (isset($apiRequest['request_body'])) {
            $requestBody = $this->httpUtility->decodeCompressedBody($apiRequest['request_body']);
        }
        $responseBody = null;
        if (isset($apiRequest['response'])) {
            $responseBody = $this->httpUtility->decodeCompressedBody($apiRequest['response']);
        }
        $gatewayRequestBody = null;
        if (isset($apiRequest['gateway_request'])) {
            $gatewayRequestBody = $this->httpUtility->decodeCompressedBody($apiRequest['gateway_request']);
        }
        $gatewayResponseBody = null;
        if (isset($apiRequest['gateway_response'])) {
            $gatewayResponseBody = $this->httpUtility->decodeCompressedBody($apiRequest['gateway_response']);
        }

        return $this->render('payment_log_viewer/view_request.html.twig', [
            'requestId' => $requestId,
            'correlationId' => $apiRequest['correlation_id'] ?? null,
            'tenantId' => $apiRequest['tenant_id'] ?? null,
            'environment' => $apiRequest['environment'],
            'method' => $apiRequest['method'],
            'endpoint' => $apiRequest['endpoint'],
            'timestamp' => $this->toUnixTimestamp($apiRequest['timestamp']),
            'ip' => $apiRequest['ip'],
            'statusCode' => $apiRequest['status_code'],
            'userAgent' => $apiRequest['user_agent'],
            'queryParams' => $apiRequest['query_params'] ?? null,
            'requestHeaders' => $apiRequest['request_headers'] ?? null,
            'responseHeaders' => $apiRequest['response_headers'] ?? null,
            'responseTime' => isset($apiRequest['response_time']) ? round($apiRequest['response_time'] / 1000.0, 3) : null,
            'requestBody' => $requestBody,
            'responseBody' => $responseBody,
            'gateway' => $apiRequest['gateway'] ?? null,
            'gatewayRequest' => $gatewayRequestBody,
            'gatewayResponse' => $gatewayResponseBody,
            'gatewayResponseTime' => isset($apiRequest['gateway_response_time']) ? round($apiRequest['gateway_response_time'] / 1000.0, 3) : null,
        ]);
    }

    /**
     * @throws DynamoDbException
     */
    private function searchLogs(DynamoDbClient $dynamodb, PaymentLogSearch $filter, string $paymentApplicationId): array
    {
        $params = [
            'TableName' => 'InvoicedPaymentLogs',
            'ProjectionExpression' => 'application_id, request_id, #timestamp, #method, endpoint, response_time, status_code, gateway',
            'ExpressionAttributeNames' => [
                '#timestamp' => 'timestamp',
                '#method' => 'method',
            ],
            'ScanIndexForward' => false,
            'TotalSegments' => 5,
        ];

        $keyConditionExpression = [];
        $filterExpression = [];
        $filterAttributeValues = [];

        if ($requestId = $filter->getRequestId()) {
            // When filtering by request ID use a dedicated index for faster results
            // This should override the tenant ID index if selected
            $params['IndexName'] = 'RequestIdIndex';
            $keyConditionExpression[] = 'request_id = :request_id';
            $filterAttributeValues[':request_id'] = ['S' => $requestId];
            $filterExpression[] = 'application_id = :applicationId';
            $filterAttributeValues[':applicationId'] = ['S' => $paymentApplicationId];
        } elseif ($correlationId = $filter->getCorrelationId()) {
            // When filtering by correlation ID use a dedicated index for faster results
            $params['IndexName'] = 'CorrelationIdIndex';
            $keyConditionExpression[] = 'correlation_id = :correlation_id';
            $filterAttributeValues[':correlation_id'] = ['S' => $correlationId];
            $filterExpression[] = 'application_id = :application_id';
            $filterAttributeValues[':application_id'] = ['S' => $paymentApplicationId];

            // If filtering by correlation ID and tenant ID then this filter must
            // be added to the query against the correlation ID index.
            if ($tenantId = $filter->getTenant()) {
                $params['ProjectionExpression'] .= ', tenant_id';
                $filterExpression[] = 'tenant_id = :tenant_id';
                $filterAttributeValues[':tenant_id'] = ['S' => $tenantId];
            }
        } elseif ($tenantId = $filter->getTenant()) {
            // When filtering by tenant ID use a dedicated index for faster results
            $params['IndexName'] = 'TenantIdIndex';
            $keyConditionExpression[] = 'tenant_id = :tenant_id';
            $filterAttributeValues[':tenant_id'] = ['S' => $tenantId];
            $filterExpression[] = 'application_id = :application_id';
            $filterAttributeValues[':application_id'] = ['S' => $paymentApplicationId];
        } else {
            $keyConditionExpression[] = 'application_id = :application_id';
            $filterAttributeValues[':application_id'] = ['S' => $filter->getApplicationId()];
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

        if ($method = $filter->getMethod()) {
            $filterExpression[] = '#method = :method';
            $filterAttributeValues[':method'] = ['S' => $method];
        }

        if ($endpoint = $filter->getEndpoint()) {
            $filterExpression[] = 'endpoint = :endpoint';
            $filterAttributeValues[':endpoint'] = ['S' => $endpoint];
        }

        if ($gateway = $filter->getGateway()) {
            $filterExpression[] = 'gateway = :gateway';
            $filterAttributeValues[':gateway'] = ['S' => $gateway];
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
