<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;
use App\Core\Utils\Enums\ObjectType;
use App\Network\Models\NetworkConnection;
use App\Sending\Email\EmailFactory\CommonEmailFactory;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Libs\EmailSender;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\Inbox;
use App\Sending\Email\Models\InboxDecorator;
use App\Sending\Email\Models\InboxEmail;
use Symfony\Component\HttpFoundation\Response;

class SendEmailRoute extends AbstractApiRoute
{
    public function __construct(
        private CommonEmailFactory $factory,
        private EmailSender $sender,
        private TenantContext $tenant,
        private string $inboundEmailDomain,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'to' => new RequestParameter(
                    types: ['array'],
                    default: [],
                ),
                'cc' => new RequestParameter(
                    types: ['array'],
                    default: [],
                ),
                'bcc' => new RequestParameter(
                    types: ['array'],
                    default: [],
                ),
                'message' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
                'related_to_type' => new RequestParameter(
                    types: ['string', 'null'],
                    default: null,
                ),
                'related_to_id' => new RequestParameter(
                    types: ['numeric', 'null'],
                    default: null,
                ),
                'network_connection' => new RequestParameter(
                    types: ['numeric', 'null'],
                    default: null,
                ),
                'thread_id' => new RequestParameter(
                    types: ['numeric', 'null'],
                    default: null,
                ),
                'subject' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
                'reply_to_id' => new RequestParameter(
                    types: ['numeric', 'null'],
                    default: null,
                ),
                'attachments' => new RequestParameter(
                    types: ['array'],
                    default: [],
                ),
                'status' => new RequestParameter(
                    types: ['string'],
                    default: EmailThread::STATUS_OPEN,
                ),
            ],
            requiredPermissions: ['emails.send'],
        );
    }

    public function buildResponse(ApiCallContext $context): InboxEmail
    {
        $inboxId = (int) $context->request->attributes->get('inbox_id');
        /** @var Inbox $inbox */
        $inbox = Inbox::findOrFail($inboxId);

        $thread = null;
        if ($threadId = $context->requestParameters['thread_id']) {
            $thread = EmailThread::findOrFail($threadId);
        }

        // In order to create a new thread, the user must have the inbox feature flag.
        // Replying to a thread does not require the inbox feature flag.
        $company = $this->tenant->get();
        if (!$thread && !$company->features->has('inboxes')) {
            throw new InvalidRequest('Your Invoiced account does not have access to this feature');
        }

        $relatedToType = null;
        if ($context->requestParameters['related_to_type']) {
            $relatedToType = ObjectType::fromTypeName($context->requestParameters['related_to_type']);
        }

        try {
            $email = $this->factory->make(
                inbox: $inbox,
                to: $this->getTo($context),
                cc: $context->requestParameters['cc'],
                bcc: $context->requestParameters['bcc'],
                subject: $context->requestParameters['subject'],
                message: $context->requestParameters['message'],
                status: $context->requestParameters['status'],
                thread: $thread,
                replyToId: $context->requestParameters['reply_to_id'],
                relatedToType: $relatedToType,
                relatedToId: $context->requestParameters['related_to_id'],
                attachments: $context->requestParameters['attachments'],
            );
            $this->sender->send($email);
        } catch (SendEmailException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        if ($resultEmail = $email->getSentEmail()) {
            return $resultEmail;
        }

        // no specific errors available, throw a generic error
        throw new ApiError('An unspecified error occurred while sending.');
    }

    public function getSuccessfulResponse(): Response
    {
        return new Response('', 201);
    }

    private function getTo(ApiCallContext $context): array
    {
        if ($connectionId = $context->requestParameters['network_connection']) {
            $connection = NetworkConnection::find($connectionId);
            if (!$connection) {
                throw new InvalidRequest('Could not find network connection: '.$connectionId);
            }

            $inbox = null;
            $company = $this->tenant->get();
            if ($connection->vendor_id == $company->id) {
                // Find the A/P inbox
                $inbox = $connection->customer->accounts_payable_settings->inbox;
            } elseif ($connection->customer_id == $company->id) {
                // Find the A/R inbox
                $inbox = $connection->vendor->accounts_receivable_settings->inbox;
            }

            if (!$inbox) {
                throw new InvalidRequest('Could not find network connection: '.$connectionId);
            }

            $decorator = new InboxDecorator($inbox, $this->inboundEmailDomain);
            $namedAddress = $decorator->getNamedEmailAddress();

            return [['name' => $namedAddress->getName(), 'email_address' => $namedAddress->getAddress()]];
        }

        return $context->requestParameters['to'];
    }
}
