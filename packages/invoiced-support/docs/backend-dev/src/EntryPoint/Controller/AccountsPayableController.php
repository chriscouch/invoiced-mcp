<?php

namespace App\EntryPoint\Controller;

use App\AccountsPayable\Models\ECheck;
use App\Core\Files\Models\VendorPaymentAttachment;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\SendECheckQueueJob;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(schemes: '%app.protocol%', host: '%app.domain%')]
class AccountsPayableController extends AbstractController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    #[Route(path: '/checks/{check_hash}', name: 'retrieve_echeck', methods: ['GET'])]
    public function ECheck(string $check_hash): Response
    {
        /** @var ?ECheck $eCheck */
        $eCheck = ECheck::queryWithoutMultitenancyUnsafe()->where('hash', $check_hash)->oneOrNull();
        if (!$eCheck) {
            throw new NotFoundHttpException('The check has not been found');
        }

        /** @var ?VendorPaymentAttachment $attachment */
        $attachment = VendorPaymentAttachment::queryWithTenant($eCheck->tenant())->where('vendor_payment_id', $eCheck->payment_id)->oneOrNull();
        if (!$attachment) {
            throw new NotFoundHttpException('The check has not been found');
        }

        if ($eCheck->isExpired()) {
            return $this->render('accounts_payable/e-check-expired.twig', [
                'hash' => $eCheck->hash,
                'refreshable' => CarbonImmutable::createFromTimestamp($eCheck->created_at)->greaterThan(CarbonImmutable::now()->subDays(90)),
            ]);
        }

        $eCheck->viewed = 1;
        $eCheck->save();

        return $this->render('accounts_payable/e-check.twig', [
            'url' => $attachment->file->url,
        ]);
    }

    #[Route(path: '/checks/{check_hash}/refresh', name: 'echeck', methods: ['GET'])]
    public function ECheckRefresh(Queue $queue, string $check_hash): Response
    {
        /** @var ?ECheck $eCheck */
        $eCheck = ECheck::queryWithoutMultitenancyUnsafe()->where('hash', $check_hash)->oneOrNull();
        if (!$eCheck) {
            throw new NotFoundHttpException('The check has not been found');
        }

        $queue->enqueue(SendECheckQueueJob::class, [
            'tenant_id' => $eCheck->tenant_id,
            'check_id' => $eCheck->id,
        ], QueueServiceLevel::Normal);

        return $this->render('accounts_payable/e-check-sent.twig');
    }
}
