<?php

namespace App\Notifications\NotificationEmails;

use App\Network\Models\NetworkInvitation;

class NetworkInvitationDeclined extends AbstractNotificationEmail
{
    private const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'Invitation to join your business network was declined';
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > self::THRESHOLD) {
            return 'notifications/network-invitation-declined-bulk';
        }

        return 'notifications/network-invitation-declined';
    }

    public function getVariables(array $events): array
    {
        if (count($events) > self::THRESHOLD) {
            return [
                'count' => count($events),
            ];
        }

        $ids = $this->getObjectIds($events);
        /** @var NetworkInvitation[] $invitations */
        $invitations = NetworkInvitation::where('id IN ('.implode(',', $ids).')')->sort('id')->execute();
        $result = [];
        foreach ($invitations as $invitation) {
            $result[] = [
                'name' => $invitation->to_company?->name ?? $invitation->email,
            ];
        }

        return [
            'invitations' => $result,
        ];
    }
}
