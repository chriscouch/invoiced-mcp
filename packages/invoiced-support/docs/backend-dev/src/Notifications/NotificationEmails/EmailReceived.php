<?php

namespace App\Notifications\NotificationEmails;

use App\Core\Utils\AppUrl;
use App\Core\Utils\Enums\ObjectType;
use App\Sending\Email\Models\InboxEmail;

class EmailReceived extends AbstractNotificationEmail
{
    const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'New message on Invoiced';
    }

    public function getVariables(array $events): array
    {
        if (count($events) > static::THRESHOLD) {
            return [
                'count' => count($events),
            ];
        }

        $ids = $this->getObjectIds($events);
        /** @var InboxEmail[] $items */
        $items = InboxEmail::where('id IN ('.implode(',', $ids).')')
            ->sort('id')
            ->execute();

        $results = [];
        $appUrl = AppUrl::get();
        foreach ($items as $item) {
            $result = $item->toArray();
            $thread = $item->thread;
            $result['customer'] = $thread->customer ? $thread->customer->toArray() : null;
            $result['inbox_id'] = $thread->inbox_id;
            $result['subject'] = $item->subject;
            $from = $item->from;
            $result['from'] = $from['name'] ?? $from['email_address'] ?? null;

            // Generate the link to the email. When possible, go to the document conversation page instead of inbox
            $result['link'] = $appUrl->getObjectLink(ObjectType::EmailThread, $thread->id, [
                'account' => $thread->tenant_id,
                'id' => $thread->inbox_id,
                'emailId' => $item->id,
            ]);
            $objectType = $thread->related_to_type;
            if ($objectType && in_array($objectType, [ObjectType::Invoice, ObjectType::CreditNote, ObjectType::Estimate, ObjectType::Bill, ObjectType::VendorCredit])) {
                $result['link'] = $appUrl->getObjectLink($objectType, (int) $thread->related_to_id).'/conversation?account='.$thread->tenant_id;
            }

            $results[] = $result;
        }

        return [
            'emails' => $results,
        ];
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > static::THRESHOLD) {
            return 'notifications/email-received-bulk';
        }

        return 'notifications/email-received';
    }
}
