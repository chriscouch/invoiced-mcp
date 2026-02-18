<?php

namespace App\Sending\Email\EmailFactory;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Files\Models\Attachment;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Model;
use App\Core\Pdf\Exception\PdfException;
use App\Core\Templating\Exception\MustacheException;
use App\Core\Templating\Exception\RenderException;
use App\Core\Templating\MustacheRenderer;
use App\Core\Templating\TwigContext;
use App\Core\Templating\TwigRenderer;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\ValueObjects\DocumentEmail;
use App\Sending\Email\ValueObjects\EmailAttachment;
use App\Sending\Email\ValueObjects\NamedAddress;
use App\Sending\Email\ValueObjects\TemplatedPart;
use App\Themes\ValueObjects\PdfTheme;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * This class creates email messages that are sent to
 * an end customer, on behalf of a business. A document,
 * like an invoice or statement, is the subject of the email.
 */
class DocumentEmailFactory extends AbstractEmailFactory implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public static ?array $emailVariables = null;

    public function __construct(
        string $inboundEmailDomain,
        private TranslatorInterface $translator,
    ) {
        parent::__construct($inboundEmailDomain);
    }

    /**
     * @throws SendEmailException
     */
    public function make(SendableDocumentInterface $document, EmailTemplate $emailTemplate, array $to, array $cc = [], ?string $bccs = null, ?string $subject = null, ?string $body = null): DocumentEmail
    {
        $company = $document->getSendCompany();
        $company->useTimezone();
        $customer = $document->getSendCustomer();
        $this->translator->setLocale($customer->getLocale());

        $to = $this->generateTo($to, $company, $customer);
        if (0 === count($to)) {
            throw new SendEmailException('No email recipients given. At least one recipient must be provided.');
        }

        // if a company is not enabled for email sending then the null adapter is always used.
        if (!$company->features->has('email_sending')) {
            throw new SendEmailException('Email sending is not enabled for your account. Please upgrade to use this feature.');
        }

        // generate the email variables
        $emailVariables = $document->getEmailVariables();
        $variables = $this->generateEmailVariables($emailVariables, $emailTemplate);
        $twigContext = $this->makeTwigContext($customer, $emailVariables);
        $compiledBody = $this->generateBody($document, $emailTemplate, $variables, $body, $twigContext);
        $templateVars = $this->getTemplateVars($company, $compiledBody);
        $compiledSubject = $this->renderSubject($emailTemplate, $variables, $subject, $twigContext);

        $email = new DocumentEmail();

        $requester = ACLModelRequester::get();
        if ($requester instanceof Member) {
            $email->sentBy($requester->user());
        }

        return $email->company($company)
            ->from(new NamedAddress((string) $company->email, $company->getDisplayName()))
            ->to($to)
            ->cc($this->generateCc([], $cc))
            ->bcc($this->generateBccs($company, $to, $bccs))
            ->document($document)
            ->emailTemplate($emailTemplate)
            ->subject($compiledSubject)
            ->body($compiledBody)
            ->html($this->renderHtml($email, $templateVars))
            ->html($this->renderHtml($email, $templateVars, true), true)
            ->plainText($this->renderPlainText($templateVars))
            ->attachments($this->generateAttachments($document, $emailTemplate))
            ->headers($this->generateDocumentHeaders($document, $email));
    }

    public function getTemplateVars(Company $company, string $body): array
    {
        $templateVars = $this->buildDefaultTemplateVars($company);
        $templateVars['body'] = $body;

        return $templateVars;
    }

    /**
     * Compiles the subject for this template with the supplied variables.
     *
     * @throws SendEmailException $e when the subject cannot be rendered
     */
    public function renderSubject(EmailTemplate $emailTemplate, array $variables, ?string $subject, TwigContext $context): string
    {
        if ($subject) {
            $emailTemplate->setSubject($subject);
        }

        $rendered = $this->renderTemplate($emailTemplate, $emailTemplate->subject, $variables, $context);

        return $this->unescapeHtml($rendered);
    }

    /**
     * Generates the message body.
     *
     * @throws SendEmailException when the body cannot be rendered
     */
    private function generateBody(SendableDocumentInterface $document, EmailTemplate $emailTemplate, array $variables, ?string $body, TwigContext $context): string
    {
        $body = $this->renderBody($emailTemplate, $variables, $body, $context);
        if ($actions = $document->schemaOrgActions()) {
            $body .= $actions;
        }

        return nl2br($body);
    }

    /**
     * Compiles this template into a message using the supplied variables.
     *
     * @throws SendEmailException $e when the message cannot be rendered
     */
    private function renderBody(EmailTemplate $emailTemplate, array $variables, ?string $body, TwigContext $context): string
    {
        if ($body) {
            $emailTemplate->setBody($body);
        }

        return $this->renderTemplate($emailTemplate, $emailTemplate->getBodyWithButton(), $variables, $context);
    }

    private function makeTwigContext(Customer $customer, EmailVariablesInterface $emailVariables): TwigContext
    {
        return new TwigContext(
            $customer->tenant(),
            $emailVariables->getCurrency(),
            $customer->moneyFormat(),
            $this->translator,
        );
    }

    /**
     * @throws SendEmailException $e when the template cannot be rendered
     */
    private function renderTemplate(EmailTemplate $emailTemplate, string $input, array $variables, TwigContext $context): string
    {
        if (PdfTheme::TEMPLATE_ENGINE_TIWG == $emailTemplate->template_engine) {
            try {
                return trim(TwigRenderer::get()->render($input, $variables, $context));
            } catch (RenderException $e) {
                throw new SendEmailException('Could not render email template due to a parsing error: '.$e->getMessage(), 0, $e);
            }
        }

        // Mustache is the default rendering engine
        try {
            return trim(MustacheRenderer::get()->render($input, $variables));
        } catch (MustacheException $e) {
            throw new SendEmailException('Could not render email template due to a parsing error: '.$e->getMessage(), 0, $e);
        }
    }

    private function renderPlainText(array $templateVars): string
    {
        return $this->unescapeHtml($templateVars['body']);
    }

    /**
     * This takes a string input which might have escaped HTML
     * presented as HTML entities. The templating system does this.
     * In non-HTML contexts (i.e. plain text message, subject line)
     * it is incorrect to add HTML escaping because the text will
     * be mangled to the viewer.
     *
     * WARNING: Do not use this transform on text displayed in an HTML context.
     */
    private function unescapeHtml(string $text): string
    {
        // strip HTML tags
        $text = strip_tags($text);

        // remove any HTML spaces
        $text = str_replace('&nbsp;', '', $text);

        // return any HTML entities to their normal form
        return html_entity_decode($text, ENT_QUOTES);
    }

    protected function renderHtml(DocumentEmail $message, array $templateVars, bool $withTracking = false): TemplatedPart
    {
        // strip the elements from the html that should
        // only be present in the plain text format
        $templateVars['body'] = $this->stripPlainTextTags($templateVars['body']);

        // injects an individualized tracking pixel if requested
        if ($withTracking) {
            $templateVars['trackingPixel'] = $message->getTrackingPixel($message->getToEmails());
        }

        return new TemplatedPart('emails/client/custom-message.twig', $templateVars);
    }

    /**
     * Generates variables to inject into the templates
     * for the subject and body.
     */
    private function generateEmailVariables(EmailVariablesInterface $emailVariables, EmailTemplate $emailTemplate): array
    {
        if (self::$emailVariables) {
            $variables = self::$emailVariables;
            self::$emailVariables = null;

            return $variables;
        }

        return $emailVariables->generate($emailTemplate);
    }

    /**
     * @return EmailAttachment[]
     */
    private function generateAttachments(SendableDocumentInterface $document, EmailTemplate $emailTemplate): array
    {
        $attachments = [];
        if ($attachment = $this->generatePdfAttachment($document, $emailTemplate)) {
            $attachments[] = $attachment;
        }

        return array_merge($attachments, $this->generateSecondaryAttachments($document, $emailTemplate));
    }

    /**
     * Generates the document PDF attachment (if enabled).
     */
    private function generatePdfAttachment(SendableDocumentInterface $document, EmailTemplate $emailTemplate): ?EmailAttachment
    {
        if (!$emailTemplate->getOption(EmailTemplateOption::ATTACH_PDF)) {
            return null;
        }

        $pdf = $document->getPdfBuilder();
        if (!$pdf) {
            return null;
        }

        $locale = $document->getSendCustomer()->getLocale();

        return new EmailAttachment($pdf->getFilename($locale), 'application/pdf', function () use ($pdf, $locale) {
            try {
                return $pdf->build($locale);
            } catch (PdfException $e) {
                $this->logger->error('Unable to build message attachments', ['exception' => $e]);

                throw new SendEmailException('Unable to build message attachments.', 0, $e);
            }
        });
    }

    /**
     * Generates the secondary file attachments (if enabled).
     *
     * @param SendableDocumentInterface|Model $document
     *
     * @return EmailAttachment[]
     */
    private function generateSecondaryAttachments(SendableDocumentInterface $document, EmailTemplate $emailTemplate): array
    {
        if (!$emailTemplate->getOption(EmailTemplateOption::ATTACH_SECONDARY_FILES)) {
            return [];
        }

        $attachments = [];
        foreach (Attachment::allForObject($document, Attachment::LOCATION_ATTACHMENT) as $attachment) {
            $file = $attachment->file();
            if ($content = $file->getContent()) {
                // The email services we use will base64 encode the attached files
                //
                // Emails are sent using the base64 encoded
                // File size is based off of the base64 encoding of the attachment
                $attachments[] = new EmailAttachment($file->name, $file->type, $content);
            }
        }

        return $attachments;
    }

    protected function generateDocumentHeaders(SendableDocumentInterface $document, DocumentEmail $message): array
    {
        $company = $document->getSendCompany();
        $headers = $this->generateHeaders($company, $message->getId(), $company->accounts_receivable_settings->reply_to_inbox);

        if ($url = $document->getSendClientUrl()) {
            $headers['X-Invoiced-Url'] = $url;
        }

        $inbox = $company->accounts_receivable_settings->inbox;
        if (!$inbox) {
            return $headers;
        }

        // locates or creates a thread for the email
        $objectType = $document->getSendObjectType();
        $emailThread = null;
        if ($objectType) {
            $emailThread = EmailThread::where('inbox_id', $inbox->id())
                ->where('related_to_type', $objectType->value)
                ->where('related_to_id', $document->getSendId())
                ->oneOrNull();
        }

        if (!$emailThread) {
            $emailThread = new EmailThread();
            $emailThread->inbox = $inbox;
            $emailThread->customer = $document->getSendCustomer();
            if ($objectType) {
                $emailThread->related_to_type = $objectType;
                $emailThread->related_to_id = $document->getSendId();
            }
            $emailThread->name = $document->getThreadName();
            $emailThread->status = EmailThread::STATUS_CLOSED;
            if (!$emailThread->save()) {
                $emailThread = null;
            }
        }

        if ($emailThread instanceof EmailThread) {
            $message->emailThread($emailThread);

            $threadId = '<'.$company->getSubdomainUsername().'/threads/'.$emailThread->id().'@invoiced.com>';
            $headers['In-Reply-To'] = $threadId;
            $headers['References'] = $threadId;
        }

        return $headers;
    }
}
