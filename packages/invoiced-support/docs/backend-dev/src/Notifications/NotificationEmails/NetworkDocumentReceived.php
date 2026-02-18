<?php

namespace App\Notifications\NotificationEmails;

use App\Network\Models\NetworkDocument;

class NetworkDocumentReceived extends AbstractNotificationEmail
{
    private const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'New document sent to you';
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > self::THRESHOLD) {
            return 'notifications/network-document-received-bulk';
        }

        return 'notifications/network-document-received';
    }

    public function getVariables(array $events): array
    {
        if (count($events) > self::THRESHOLD) {
            return [
                'count' => count($events),
            ];
        }

        $ids = $this->getObjectIds($events);
        /** @var NetworkDocument[] $documents */
        $documents = NetworkDocument::where('id IN ('.implode(',', $ids).')')->sort('id')->execute();
        $result = [];
        foreach ($documents as $document) {
            $result[] = [
                'id' => $document->id,
                'from' => $document->from_company->name,
                'type' => $document->type->name,
                'reference' => $document->reference,
            ];
        }

        return [
            'documents' => $result,
        ];
    }
}
