<?php

namespace App\Notifications\NotificationEmails;

use App\Network\Models\NetworkConnection;

class NetworkInvitationAccepted extends AbstractNotificationEmail
{
    private const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'Your business network is growing!';
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > self::THRESHOLD) {
            return 'notifications/network-invitation-accepted-bulk';
        }

        return 'notifications/network-invitation-accepted';
    }

    public function getVariables(array $events): array
    {
        if (count($events) > self::THRESHOLD) {
            return [
                'count' => count($events),
            ];
        }

        $ids = $this->getObjectIds($events);
        /** @var NetworkConnection[] $connections */
        $connections = NetworkConnection::where('id IN ('.implode(',', $ids).')')->sort('id')->execute();
        $result = [];
        $tenant = count($events) > 0 ? $events[0]->tenant() : null;
        foreach ($connections as $connection) {
            $result[] = [
                'name' => $tenant ? $connection->getCounterparty($tenant)->name : null,
            ];
        }

        return [
            'connections' => $result,
        ];
    }
}
