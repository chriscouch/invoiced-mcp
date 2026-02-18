<?php

namespace App\Sending\Email\Traits;

use App\Sending\Email\Interfaces\IsEmailParticipantInterface;
use App\Sending\Email\Models\EmailParticipant;
use App\Core\Orm\Event\AbstractEvent;

/**
 * Save or update email participant when appropriate model is changed
 * Trait IsEmailParticipantTrait.
 */
trait IsEmailParticipantTrait
{
    public function autoInitializeEmailParticipant(): void
    {
        self::saved([static::class, 'saveEmailParticipant'], -256);
    }

    public static function saveEmailParticipant(AbstractEvent $event): void
    {
        /** @var IsEmailParticipantInterface $model */
        $model = $event->getModel();
        if ($email = $model->getEmail()) {
            $name = $model->getName();
            $company = $model->tenant();
            $participant = EmailParticipant::getOrCreate($company, $email, $name);

            // Always use the company name if the company email address is a participant.
            if ($email == $company->email) {
                $name = $company->getDisplayName();
            }

            // Update the name on an existing participant if it has changed
            if ($participant->name != $name) {
                $participant->name = $name;
                $participant->save();
            }
        }
    }
}
