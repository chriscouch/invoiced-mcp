<?php

namespace App\Core\Mailer;

use App\Sending\Email\Exceptions\EmailLimitException;
use App\Sending\Email\Interfaces\EmailInterface;
use Doctrine\DBAL\Connection;

class EmailBlockList
{
    public function __construct(private Connection $database)
    {
    }

    /**
     * Checks if an email address is blocked.
     */
    public function isBlocked(string $email): ?EmailBlockReason
    {
        $reason = $this->database->fetchOne('SELECT reason FROM BlockListEmailAddresses WHERE email=? AND (reason='.EmailBlockReason::PermanentBounce->value.' OR (reason='.EmailBlockReason::Complaint->value.' AND complaint_count >= 3))', [$email]);

        return $reason ? EmailBlockReason::from($reason) : null;
    }

    /**
     * Checks if any recipient in an email is blocked.
     *
     * @throws EmailLimitException
     */
    public function checkForBlockedAddress(EmailInterface $email): void
    {
        $to = array_filter($email->getTo(), fn ($address) => !$this->isBlocked($address->getAddress()));
        $cc = array_filter($email->getCc(), fn ($address) => !$this->isBlocked($address->getAddress()));
        $bcc = array_filter($email->getBcc(), fn ($address) => !$this->isBlocked($address->getAddress()));

        if (empty($to) && empty($cc) && empty($bcc)) {
            throw new EmailLimitException('Sending emails to '.implode(', ', array_unique([...$email->getTo(), ...$email->getCc(), ...$email->getBcc()])).' is blocked');
        }

        $email->to(array_values($to));
        $email->cc(array_values($cc));
        $email->bcc(array_values($bcc));
    }

    /**
     * Blocks an email address.
     */
    public function block(string $email, EmailBlockReason $reason): void
    {
        $this->database->executeStatement('INSERT IGNORE INTO BlockListEmailAddresses (email, reason) VALUES (?, ?)', [$email, $reason->value]);

        if (EmailBlockReason::Complaint == $reason) {
            $this->database->executeStatement('UPDATE BlockListEmailAddresses SET complaint_count=complaint_count + 1 WHERE email=?', [$email]);
        }
    }
}
