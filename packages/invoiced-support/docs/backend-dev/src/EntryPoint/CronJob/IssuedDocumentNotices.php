<?php

namespace App\EntryPoint\CronJob;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\AccountsReceivable\Libs\IssuedDocumentNotifier;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Sending\Email\Libs\EmailSpool;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;
use App\Core\Orm\Iterator;
use App\Core\Orm\Query;

class IssuedDocumentNotices extends AbstractTaskQueueCronJob
{
    private const BATCH_SIZE = 200;

    public function __construct(
        private TenantContext $tenant,
        private IssuedDocumentNotifier $notifier,
        private EmailSpool $emailSpool,
    ) {
    }

    public static function getName(): string
    {
        return 'send_issued_documents';
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        return $this->getWithNotifications();
    }

    /**
     * @param EmailTemplateOption $task
     */
    public function runTask(mixed $task): bool
    {
        $company = $task->tenant();

        // check if the company is in good standing
        if (!$company->billingStatus()->isActive()) {
            return false;
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        $template = $task->relation('template');
        $sendOnIssue = (bool) $template->getOption(EmailTemplateOption::SEND_ON_ISSUE);
        $reminderDays = (int) $template->getOption(EmailTemplateOption::SEND_REMINDER_DAYS);

        if (EmailTemplate::NEW_INVOICE == $task->template) {
            $sendOnAutoPay = (bool) $template->getOption(EmailTemplateOption::SEND_ON_AUTOPAY_INVOICE);
            $cutoffDate = (int) $template->getOption(EmailTemplateOption::CUTOFF_DATE);
            $this->sendInvoices($company, $sendOnIssue, $reminderDays, $sendOnAutoPay, $cutoffDate);
        } elseif (EmailTemplate::PAYMENT_PLAN == $task->template) {
            $this->sendPaymentPlans($company, $sendOnIssue, $reminderDays);
        } elseif (EmailTemplate::ESTIMATE == $task->template) {
            $this->sendEstimates($company, $sendOnIssue, $reminderDays);
        } elseif (EmailTemplate::CREDIT_NOTE == $task->template) {
            $this->sendCreditNotes($company);
        }

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();

        return true;
    }

    /**
     * Gets all of the `send_on_issue` email template options
     * that are enabled.
     *
     * @return Iterator<EmailTemplateOption>
     */
    public function getWithNotifications(): Iterator
    {
        return EmailTemplateOption::queryWithoutMultitenancyUnsafe()
            ->join(Company::class, 'tenant_id', 'Companies.id')
            ->where('((`option` = "'.EmailTemplateOption::SEND_ON_ISSUE.'" AND value = "1") OR (`option` = "'.EmailTemplateOption::SEND_REMINDER_DAYS.'" AND value <> "0"))')
            ->where('Companies.canceled=0')
            ->all();
    }

    /**
     * Sends out all newly issued credit notes.
     *
     * @return int # of credit notes sent
     */
    public function sendCreditNotes(Company $company): int
    {
        return $this->send($this->notifier->getCreditNotes($company));
    }

    /**
     * Sends out all newly issued estimates.
     *
     * @param int $reminderDays sends a reminder every N days if N > 0
     *
     * @return int # of invoices sent
     */
    public function sendEstimates(Company $company, bool $sendNewlyIssued, int $reminderDays): int
    {
        if ($query = $this->notifier->getEstimates($company, $sendNewlyIssued, $reminderDays)) {
            return $this->send($query);
        }

        return 0;
    }

    /**
     * Sends out new invoices and reminders.
     *
     * @param int $reminderDays sends a reminder every N days if N > 0
     */
    public function sendInvoices(Company $company, bool $sendNewlyIssued, int $reminderDays, bool $sendOnAutoPay, ?int $cutoffDate = null): int
    {
        if ($query = $this->notifier->getInvoices($company, $sendNewlyIssued, $reminderDays, $sendOnAutoPay, $cutoffDate)) {
            return $this->send($query);
        }

        return 0;
    }

    /**
     * Sends out all newly issued payment plan invoices or
     * those that need a reminder.
     *
     * @param int $reminderDays sends a reminder every N days if N > 0
     */
    public function sendPaymentPlans(Company $company, bool $sendNewlyIssued, int $reminderDays): int
    {
        if ($query = $this->notifier->getPaymentPlans($company, $sendNewlyIssued, $reminderDays)) {
            return $this->send($query);
        }

        return 0;
    }

    /**
     * Sends a batch of documents.
     */
    private function send(Query $query): int
    {
        /** @var ReceivableDocument[] $documents */
        $documents = $query->first(self::BATCH_SIZE);

        $n = 0;
        foreach ($documents as $document) {
            // if an error happens in this job we ignore it
            // because it will be retried in the next job
            // and depending on the error might already be logged
            $emailTemplate = (new DocumentEmailTemplateFactory())->get($document);
            $this->emailSpool->spoolDocument($document, $emailTemplate, [], false);
            ++$n;
        }

        return $n;
    }
}
