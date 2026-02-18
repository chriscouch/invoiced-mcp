<?php

namespace App\Sending\Mail\Api;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\I18n\AddressFormatter;
use App\Core\Multitenant\TenantContext;
use App\Sending\Mail\Exceptions\SendLetterException;
use App\Sending\Mail\Libs\LetterSender;
use App\Statements\Libs\AbstractStatement;
use CommerceGuys\Addressing\Address;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * API route to send physical letters.
 */
class SendLetterRoute extends AbstractRetrieveModelApiRoute implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private TenantContext $tenant,
        private LetterSender $sender,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['letters.send'],
        );
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
        parent::buildResponse($context);

        $model = $this->getSendModel();
        if ($model instanceof AbstractStatement) {
            $customer = $model->customer;
        } else {
            $customer = $model->customer();
        }

        // Build From address
        $formatter = new AddressFormatter();
        $formatter->setFrom($this->tenant->get());
        $from = $formatter->buildAddress();

        // Build To address using either:
        // 1. Supplied from request
        // 2. From customer profile
        if (isset($context->requestParameters['address1'])) {
            $to = $this->buildToAddress($customer, $context);
        } else {
            $formatter->setTo($customer);
            $to = $formatter->buildAddress();
        }

        try {
            return $this->sender->send($customer, $model, $from, $to);
        } catch (SendLetterException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }

    public function getSuccessfulResponse(): Response
    {
        return new Response('', 201);
    }

    /**
     * Builds a single use to address (not stored on customer).
     */
    private function buildToAddress(Customer $customer, ApiCallContext $context): Address
    {
        $to = new Address();
        $to = $to->withGivenName($context->requestParameters['name'] ?? $customer->name)
            ->withAddressLine1($context->requestParameters['address1'])
            ->withAdministrativeArea($context->requestParameters['state'])
            ->withPostalCode($context->requestParameters['postal_code'])
            ->withCountryCode($context->requestParameters['country']);

        if (isset($context->requestParameters['address2'])) {
            $to = $to->withAddressLine2($context->requestParameters['address2']);
        }

        if (isset($context->requestParameters['city'])) {
            $to = $to->withLocality($context->requestParameters['city']);
        }

        return $to;
    }
}
