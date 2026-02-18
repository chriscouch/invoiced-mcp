<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Mailer\EmailBlockList;
use App\Core\Multitenant\TenantContext;
use App\Core\Utils\DebugContext;
use App\Sending\Email\Adapter\SmtpAdapter;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Models\SmtpAccount;
use App\Sending\Email\ValueObjects\Email;
use App\Sending\Email\ValueObjects\NamedAddress;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Response;

class TestSmtpSettingsRoute extends AbstractApiRoute implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private TenantContext $tenant,
        private CloudWatchLogsClient $cloudWatchLogsClient,
        private DebugContext $debugContext,
        private EmailBlockList $blockList,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            features: ['email_sending'],
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $smtpAccount = new SmtpAccount([
            'host' => (string) $context->request->request->get('host'),
            'username' => (string) $context->request->request->get('username'),
            'password' => (string) $context->request->request->get('password'),
            'port' => (int) $context->request->request->get('port'),
            'encryption' => (string) $context->request->request->get('encryption'),
            'auth_mode' => (string) $context->request->request->get('auth_mode'),
        ]);

        $adapter = new SmtpAdapter($smtpAccount, $this->cloudWatchLogsClient, $this->debugContext, $this->blockList, false);
        $adapter->setLogger($this->logger);

        // Sending is tested by sending a test email
        try {
            $testEmail = (new Email())
                ->from(new NamedAddress((string) $this->tenant->get()->email))
                ->to([new NamedAddress('no-reply@invoiced.com')])
                ->subject('Invoiced Email Sending Test')
                ->plainText('This is a test message to verify that email sending through Invoiced is working.')
                ->company($this->tenant->get());
            $adapter->send($testEmail);
        } catch (SendEmailException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return new Response('', 204);
    }
}
