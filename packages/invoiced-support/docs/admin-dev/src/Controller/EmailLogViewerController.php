<?php

namespace App\Controller;

use App\Entity\CustomerAdmin\User;
use App\Entity\Forms\EmailLogSearch;
use App\Entity\Invoiced\BlockListEmailAddress;
use App\Form\SearchEmailLogsType;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\Sdk;
use Aws\SesV2\Exception\SesV2Exception;
use Carbon\CarbonImmutable;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Throwable;

class EmailLogViewerController extends AbstractController
{
    private function getForm(Request $request): FormInterface
    {
        $data = new EmailLogSearch();
        if ($email = $request->query->get('email')) {
            $data->setEmail((string) $email);
        }

        $form = $this->createForm(SearchEmailLogsType::class, $data);

        $form->handleRequest($request);

        return $form;
    }

    #[Route(path: '/admin/email_logs', name: 'email_log_viewer')]
    public function newSearch(Request $request, Security $security, Sdk $sdk, LoggerInterface $logger, string $emailRegion, ManagerRegistry $doctrine): Response
    {
        // use the signed in user's time zone
        /** @var User $user */
        $user = $security->getUser();
        date_default_timezone_set($user->getTimeZone());
        $form = $this->getForm($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $filter = $form->getData();

            return $this->searchResults($filter, $security, $sdk, $emailRegion, $logger, $doctrine);
        }

        return $this->render('email_log_viewer/new_search.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/admin/email_logs/{email}', name: 'email_log_search')]
    public function lookUpEmail(string $email, Security $security, Sdk $sdk, string $emailRegion, LoggerInterface $logger, ManagerRegistry $doctrine): Response
    {
        $filter = new EmailLogSearch();
        $filter->setEmail($email);

        return $this->searchResults($filter, $security, $sdk, $emailRegion, $logger, $doctrine);
    }

    #[Route(path: '/admin/email_logs/{email}/delete_suppression', name: 'remove_from_email_suppression_list')]
    public function removeFromSuppressionList(string $email, AdminUrlGenerator $adminUrlGenerator, Sdk $sdk, string $emailRegion, LoggerInterface $logger, ManagerRegistry $doctrine): Response
    {
        // Remove from our internal block list
        $em = $doctrine->getManager('Invoiced_ORM');
        $repository = $em->getRepository(BlockListEmailAddress::class);
        if ($block = $repository->findOneBy(['email' => $email])) {
            $em->remove($block);
            $em->flush();
        }

        // Attempt to remove from AWS account suppression list.
        // The email address might not be on the list.
        $sesClient = $sdk->createSesV2(['region' => $emailRegion]);

        try {
            $sesClient->deleteSuppressedDestination([
                'EmailAddress' => $email,
            ]);
        } catch (SesV2Exception $e) {
            $response = $e->getResponse();
            if (404 != $response?->getStatusCode()) {
                $logger->error('Could not delete suppressed email', ['exception' => $e]);
                $message = $e->getMessage();

                $filter = new EmailLogSearch();
                $filter->setEmail($email);

                return $this->render('email_log_viewer/search_results.html.twig', [
                    'filter' => $filter,
                    'error' => $message,
                ]);
            }
        }

        $this->addFlash('success', 'Removed '.$email.' from any block lists');

        $url = $adminUrlGenerator->setRoute('email_log_search', ['email' => $email])
            ->generateUrl();

        return $this->redirect($url);
    }

    private function searchResults(EmailLogSearch $filter, Security $security, Sdk $sdk, string $emailRegion, LoggerInterface $logger, ManagerRegistry $doctrine): Response
    {
        // use the signed in user's time zone
        /** @var User $user */
        $user = $security->getUser();
        date_default_timezone_set($user->getTimeZone());

        $em = $doctrine->getManager('Invoiced_ORM');
        $repository = $em->getRepository(BlockListEmailAddress::class);
        $block = $repository->findOneBy(['email' => $filter->getEmail()]);

        $dynamodb = $sdk->createDynamoDb(['region' => $emailRegion]);

        try {
            $activity = $this->getEmailLogs($filter, $dynamodb);
        } catch (Throwable $e) {
            $logger->error('DynamoDB query failed', ['exception' => $e]);

            return $this->render('email_log_viewer/search_results.html.twig', [
                'filter' => $filter,
                'block' => $block,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->render('email_log_viewer/search_results.html.twig', [
            'filter' => $filter,
            'block' => $block,
            'activity' => $activity,
        ]);
    }

    private function getEmailLogs(EmailLogSearch $filter, DynamoDbClient $dynamodb): array
    {
        $params = [
            'TableName' => 'SesDeliveries',
            'ProjectionExpression' => 'email, subject, #timestamp, #type, #from, #to, messageId',
            'ExpressionAttributeNames' => [
                '#timestamp' => 'timestamp',
                '#type' => 'type',
                '#from' => 'from',
                '#to' => 'to',
            ],
            'ScanIndexForward' => false,
            'TotalSegments' => 5,
        ];

        $keyConditionExpression = ['email = :email'];
        $filterAttributeValues = [
            ':email' => ['S' => $filter->getEmail()],
        ];

        // Time ranges are in viewer's time zone. These
        // MUST be converted to UTC before querying DynamoDB.
        $startTime = $filter->getStartTimeUtc();
        $endTime = $filter->getEndTimeUtc();
        if ($startTime && $endTime) {
            $keyConditionExpression[] = '#timestamp BETWEEN :start_time AND :end_time';
            $filterAttributeValues[':start_time'] = ['S' => $startTime->format(EmailLogSearch::DATE_FORMAT)];
            $filterAttributeValues[':end_time'] = ['S' => $endTime->format(EmailLogSearch::DATE_FORMAT)];
        } elseif ($startTime) {
            $keyConditionExpression[] = '#timestamp >= :start_time';
            $filterAttributeValues[':start_time'] = ['S' => $startTime->format(EmailLogSearch::DATE_FORMAT)];
        } elseif ($endTime) {
            $keyConditionExpression[] = '#timestamp <= :end_time';
            $filterAttributeValues[':end_time'] = ['S' => $endTime->format(EmailLogSearch::DATE_FORMAT)];
        }

        $params['KeyConditionExpression'] = join(' and ', $keyConditionExpression);
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
                $item['timestamp'] = (new CarbonImmutable($item['timestamp']))->getTimestamp();
                $resultSet[] = $item;
            }
            $lastKey = $result['LastEvaluatedKey'] ?? null;
            $hasMore = isset($result['LastEvaluatedKey']);
        }

        return $resultSet;
    }
}
