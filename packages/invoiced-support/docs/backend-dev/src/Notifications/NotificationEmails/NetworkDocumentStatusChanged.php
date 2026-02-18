<?php

namespace App\Notifications\NotificationEmails;

use App\Network\Models\NetworkDocumentStatusTransition;

class NetworkDocumentStatusChanged extends AbstractNotificationEmail
{
    private const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'Document status has been updated';
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > self::THRESHOLD) {
            return 'notifications/network-document-status-changed-bulk';
        }

        return 'notifications/network-document-status-changed';
    }

    public function getVariables(array $events): array
    {
        if (count($events) > self::THRESHOLD) {
            return [
                'count' => count($events),
            ];
        }

        $ids = $this->getObjectIds($events);
        /** @var NetworkDocumentStatusTransition[] $transitions */
        $transitions = NetworkDocumentStatusTransition::where('id IN ('.implode(',', $ids).')')->sort('id')->execute();
        $result = [];
        foreach ($transitions as $transition) {
            $document = $transition->document;
            $result[] = [
                'id' => $document->id,
                'from' => $transition->company->name,
                'type' => $document->type->name,
                'status' => $transition->status->name,
                'reference' => $document->reference,
                'description' => $transition->description,
            ];
        }

        return [
            'documents' => $result,
        ];
    }
}
