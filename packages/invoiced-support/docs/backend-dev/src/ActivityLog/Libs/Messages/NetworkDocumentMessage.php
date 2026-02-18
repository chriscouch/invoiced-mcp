<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class NetworkDocumentMessage extends BaseMessage
{
    protected function networkDocumentSent(): array
    {
        $toName = array_value($this->object, 'to_company.name') ?? '[deleted account]';

        return [
            $this->networkDocument(),
            new AttributedString(' was sent to '.$toName),
        ];
    }

    protected function networkDocumentReceived(): array
    {
        $fromName = array_value($this->object, 'from_company.name') ?? '[deleted account]';

        return [
            $this->networkDocument(),
            new AttributedString(' was received from '.$fromName),
        ];
    }

    protected function networkDocumentStatusUpdated(): array
    {
        return [
            $this->networkDocument(),
            new AttributedString(' status was updated: '.array_value($this->object, 'current_status')),
        ];
    }

    private function networkDocument(): AttributedObject
    {
        // try to get the name from the document object
        $name = array_value($this->object, 'type').' '.array_value($this->object, 'reference');

        // if all else fails, then use the generic deleted name
        if (empty(trim($name))) {
            $name = '[deleted document]';
        }

        return new AttributedObject('network_document', $name, array_value($this->associations, 'network_document'));
    }
}
