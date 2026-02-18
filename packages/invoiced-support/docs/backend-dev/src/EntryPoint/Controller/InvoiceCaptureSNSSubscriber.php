<?php

namespace App\EntryPoint\Controller;

use App\Core\Multitenant\TenantContext;
use App\Integrations\Textract\Libs\AnalyzerFactory;
use App\Integrations\Textract\Libs\InvoiceCaptureLock;
use App\Integrations\Textract\Models\TextractImport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Routing\Attribute\Route;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;

#[Route(name: 'invoice_capture_', schemes: '%app.protocol%', host: '%app.domain%')]
class InvoiceCaptureSNSSubscriber extends AbstractController
{
    const LOCK_TTL = 100;

    #[Route(path: '/document/capture/receive', name: 'document_capture_receive_in', methods: ['POST'])]
    public function receive(AnalyzerFactory $analyzerFactory, LockFactory $lockFactory, TenantContext $tenantContext): Response
    {
        $message = Message::fromRawPostData();
        $validator = new MessageValidator();
        if (!$validator->isValid($message)) {
            return new Response('Invalid message', 403);
        }

        if ('SubscriptionConfirmation' === $message->offsetGet('Type')) {
            @file_get_contents($message->offsetGet('SubscribeURL'));

            return new Response();
        }

        if ('Notification' === $message->offsetGet('Type')) {
            $data = json_decode($message->offsetGet('Message'), true);
            $jobId = $data['JobId'];
            $status = $data['Status'];
            $api = $data['API'];

            if ('SUCCEEDED' !== $status) {
                return new Response();
            }

            $lock = new InvoiceCaptureLock($jobId, $lockFactory);
            if (!$lock->acquire(self::LOCK_TTL)) {
                return new Response('Duplicated request', 409);
            }

            $job = TextractImport::queryWithoutMultitenancyUnsafe()->where('job_id', $jobId)->one();

            $tenantContext->set($job->tenant());

            $analyzer = $analyzerFactory->getByApi($api);
            if (!$analyzer->validate($job)) {
                return new Response();
            }

            $analyzer->analyze($job);

            $lock->release();

            return new Response();
        }

        return new Response('Unknown notification type', 404);
    }
}
