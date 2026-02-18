<?php

namespace App\Chasing\CustomerChasing\Actions;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\ContactRole;
use App\Chasing\CustomerChasing\ChasingStatementStrategy;
use App\Chasing\ValueObjects\ActionResult;
use App\Chasing\ValueObjects\ChasingEvent;
use App\Sending\Email\EmailFactory\DocumentEmailFactory;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Libs\EmailSender;
use App\Statements\Libs\StatementBuilder;

/**
 * Chasing action to send email statements
 * to open accounts.
 */
class Email extends AbstractAction
{
    const MAX_INVOICES_THRESHOLD = 10;

    public function __construct(private DocumentEmailFactory $factory, private EmailSender $sender, private StatementBuilder $builder)
    {
    }

    public function execute(ChasingEvent $event): ActionResult
    {
        $emailTemplate = $event->getStep()->emailTemplate();
        if (!$emailTemplate) {
            return new ActionResult(false, 'Email template not found');
        }

        $to = $this->getEmailContacts($event);
        if (0 == count($to)) {
            return new ActionResult(false, 'No recipients');
        }
        $statement = new ChasingStatementStrategy($event);
        if (count($event->getInvoices()) > self::MAX_INVOICES_THRESHOLD) {
            $statement->setStrategy($this->builder->openItem($event->getCustomer()));
        }

        try {
            $email = $this->factory->make($statement, $emailTemplate, $to);
            $this->sender->send($email);

            return new ActionResult(true);
        } catch (SendEmailException $e) {
            return new ActionResult(false, $e->getMessage());
        }
    }

    private function getEmailContacts(ChasingEvent $event): array
    {
        $contactRole = $event->getStep()->role;
        if (!($contactRole instanceof ContactRole)) {
            return $event->getCustomer()->emailContacts();
        }

        $contacts = Contact::where('customer_id', $event->getCustomer()->id())
            ->where('role_id', $contactRole->id())
            ->all();

        $to = [];
        /** @var Contact $contact */
        foreach ($contacts as $contact) {
            if (!$contact->email) {
                continue;
            }

            $to[] = [
                'name' => $contact->name,
                'email' => $contact->email,
            ];
        }

        return $to;
    }
}
