<?php

namespace App\Sending\Sms\Api;

use App\AccountsReceivable\Models\Invoice;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Sms\Exceptions\SendSmsException;
use App\Sending\Sms\Libs\TextMessageSender;
use App\Statements\Libs\AbstractStatement;
use Symfony\Component\HttpFoundation\Response;

class SendTextMessageRoute extends AbstractRetrieveModelApiRoute
{
    protected array $to = [];
    protected ?string $message = null;

    public function __construct(private TextMessageSender $sender)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['text_messages.send'],
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

    public function buildResponse(ApiCallContext $context): mixed
    {
        if ($to = $context->request->request->all('to')) {
            $this->to = $to;
        }

        if ($message = (string) $context->request->request->get('message')) {
            $this->message = $message;
        }

        parent::buildResponse($context);

        if (!$this->message) {
            throw new InvalidRequest('A message must be provided.');
        }

        $model = $this->getSendModel();

        if ($model instanceof Invoice) {
            $variables = $this->getInvoiceVariables($model);
            $customer = $model->customer();
        } elseif ($model instanceof AbstractStatement) {
            $variables = $this->getStatementVariables($model);
            $customer = $model->customer;
        } else {
            throw new InvalidRequest('Invalid send model type');
        }

        try {
            return $this->sender->send($customer, $model, $this->to, $this->message, $variables, null);
        } catch (SendSmsException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }

    public function getSuccessfulResponse(): Response
    {
        return new Response('', 201);
    }

    public function getInvoiceVariables(Invoice $invoice): array
    {
        $customer = $invoice->customer();
        $company = $invoice->getSendCompany();

        // TODO shorten this URL
        $url = $invoice->url;

        return [
            'company_name' => $company->getDisplayName(),
            'customer_name' => $customer->name,
            'customer_number' => $customer->number,
            'invoice_number' => $invoice->number,
            'total' => $invoice->total,
            'balance' => $invoice->balance,
            'url' => $url,
        ];
    }

    public function getStatementVariables(AbstractStatement $statement): array
    {
        $customer = $statement->customer;
        $company = $statement->getSendCompany();

        // TODO shorten this URL
        $url = $customer->statement_url;

        return [
            'company_name' => $company->getDisplayName(),
            'customer_name' => $customer->name,
            'customer_number' => $customer->number,
            'balance' => $statement->balance,
            'url' => $url,
        ];
    }
}
