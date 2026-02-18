<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Mailer\EmailBlockList;
use App\Core\Multitenant\TenantContext;
use App\Sending\Email\EmailFactory\DocumentEmailFactory;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Sending\Email\Libs\EmailSpool;
use App\Sending\Email\Models\EmailTemplate;
use Symfony\Component\HttpFoundation\Response;

class SendDocumentEmailRoute extends AbstractRetrieveModelApiRoute
{
    protected array $to = [];
    protected ?string $bcc = null;
    private ?string $emailTemplateId = null;
    protected ?string $subject = null;
    protected ?string $message = null;

    public function __construct(
        private TenantContext $tenant,
        private DocumentEmailFactory $factory,
        private EmailSpool $emailSpool,
        private EmailBlockList $blockList,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['emails.send'],
        );
    }

    /**
     * Gets the recipients.
     */
    public function getTo(): array
    {
        return $this->to;
    }

    /**
     * Gets the email template ID.
     */
    public function getEmailTemplateId(): ?string
    {
        return $this->emailTemplateId;
    }

    /**
     * Sets the email template ID.
     */
    public function setEmailTemplateId(?string $template): void
    {
        $this->emailTemplateId = $template;
    }

    /**
     * Gets the BCC recipients.
     */
    public function getBcc(): ?string
    {
        return $this->bcc;
    }

    /**
     * Gets the subject.
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }

    /**
     * Gets the message.
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Gets the model to be used for sending.
     */
    public function getSendModel(): mixed
    {
        return $this->model;
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $requestParameters = $context->request->request->all();
        $to = $requestParameters['to'] ?? [];
        if ($to && is_array($to)) {
            $this->to = $to;
        }
        if ($to && is_string($to)) {
            $this->to = [['email' => $to]];
        }

        $this->bcc = ((string) $context->request->request->get('bcc')) ?: null;

        if ($emailTemplateId = (string) $context->request->request->get('template')) {
            $this->setEmailTemplateId($emailTemplateId);
        }

        if ($subject = (string) $context->request->request->get('subject')) {
            $this->subject = $subject;
        }

        if ($message = (string) $context->request->request->get('message')) {
            $this->message = $message;
        }

        parent::buildResponse($context);

        $model = $this->getSendModel();
        $company = $this->tenant->get();

        if ($this->getEmailTemplateId()) {
            $emailTemplate = EmailTemplate::make($company->id, $this->getEmailTemplateId());
        } else {
            $emailTemplate = (new DocumentEmailTemplateFactory())->get($model);
        }

        $useInvoicedEmailProvider = 'smtp' != $company->accounts_receivable_settings->email_provider;
        if (0 == count($this->to)) {
            // When there is not a set of contacts provided then we
            // use the default contact list for the document being sent.
            // When using the default contact list we want to exclude
            // any email address that is
            foreach ($model->getDefaultEmailContacts() as $to) {
                if (!$useInvoicedEmailProvider || !$this->blockList->isBlocked($to['email'])) {
                    $this->to[] = $to;
                }
            }
        }

        try {
            $email = $this->factory->make($model, $emailTemplate, $this->to, [], $this->bcc, $this->subject, $this->message);

            if ($useInvoicedEmailProvider) {
                $this->blockList->checkForBlockedAddress($email);
            }

            $this->emailSpool->spool($email);
        } catch (SendEmailException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        // At this point the email has not yet been sent.
        // This result should not be utilized by the client.
        // We are keeping this here for BC.
        return [$email->toInboxEmail()];
    }

    public function getSuccessfulResponse(): Response
    {
        return new Response('', 201);
    }
}
