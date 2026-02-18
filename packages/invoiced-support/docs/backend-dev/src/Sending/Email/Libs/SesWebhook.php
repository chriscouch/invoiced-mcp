<?php

namespace App\Sending\Email\Libs;

use App\Core\Mailer\EmailBlockList;
use App\Core\Mailer\EmailBlockReason;
use App\Core\Multitenant\TenantContext;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Sending\Email\Models\InboxEmail;
use Symfony\Component\HttpFoundation\Request;

/**
 * This class receives and processes events from the AWS SES webhook.
 */
class SesWebhook implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private TenantContext $tenant,
        private BounceEmailWriter $bounceEmailWriter,
        private EmailBlockList $blockList,
    ) {
    }

    /**
     * Handles a webhook from our aws-ses-logger lambda function.
     */
    public function handle(Request $request): void
    {
        $event = $request->request->all();
        if ('Bounce' == $event['type']) {
            $this->handleBounce($event);
        } elseif ('Complaint' == $event['type']) {
            $this->handleComplaint($event);
        }
    }

    public function handleBounce(array $event): void
    {
        $bounceType = strtolower($event['bounceDetails']['bounceType'] ?? '');
        $this->statsd->increment('email.bounced', 1.0, ['bounce_type' => $bounceType]);

        // Block the email address if it's a permanent bounce
        if ('permanent' == $bounceType) {
            $this->blockList->block($event['email'], EmailBlockReason::PermanentBounce);
        }

        // Look for a matching email based on message ID
        // only proceeding if there is exactly 1 matching email
        $messageId = $event['awsMessageId'] ?? '';
        $email = $this->getEmail($messageId);
        if (!$email) {
            return;
        }

        $this->bounceEmailWriter->write($event['email'], $this->getBounceReason($event['bounceDetails']), $email);

        $email->bounce = true;
        $email->saveOrFail();

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();
    }

    public function handleComplaint(array $event): void
    {
        $this->statsd->increment('email.complaint', 1.0, ['complaint_type' => $event['complaintDetails']['complaintFeedbackType'] ?? '']);

        // Block the email address
        $this->blockList->block($event['email'], EmailBlockReason::Complaint);

        // Look for a matching email based on message ID
        // only proceeding if there is exactly 1 matching email
        $messageId = $event['awsMessageId'] ?? '';
        $email = $this->getEmail($messageId);
        if (!$email) {
            return;
        }

        $email->complaint = true;
        $email->saveOrFail();

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();
    }

    private function getEmail(string $messageId): ?InboxEmail
    {
        if (!$messageId) {
            return null;
        }

        /** @var InboxEmail[] $emails */
        $emails = InboxEmail::queryWithoutMultitenancyUnsafe()
            ->where('message_id', $messageId)
            ->first(2);
        if (1 != count($emails)) {
            return null;
        }

        $email = $emails[0];

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($email->tenant());

        return $email;
    }

    private function getBounceReason(array $bounceDetails): string
    {
        $result = [];
        foreach ($bounceDetails as $key => $value) {
            if (is_array($value)) {
                $result[] = $this->getBounceReason($value);
            } else {
                $result[] = "$key: $value";
            }
        }

        return implode("\n", $result);
    }
}
