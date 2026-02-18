<?php

namespace App\Sending\Email\Libs;

use App\Companies\Models\Company;
use App\Sending\Email\Models\EmailParticipant;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

abstract class AbstractEmailWriter implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(protected EmailBodyStorageInterface $emailBodyStorage, protected Connection $database)
    {
    }

    protected function createAssociation(Company $company, int $emailId, string $email, ?string $name, string $type): void
    {
        $this->database->executeStatement('INSERT INTO EmailParticipantAssociations (email_id, participant_id, `type`) VALUES (:emailId, :participantId, :type) ON DUPLICATE KEY UPDATE email_id=VALUES(email_id)', [
            'emailId' => $emailId,
            'participantId' => EmailParticipant::getOrCreate($company, $email, $name ?? '')->id(),
            'type' => $type,
        ]);
    }
}
