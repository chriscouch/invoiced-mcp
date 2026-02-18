<?php

namespace App\Sending\Email\Libs;

use App\Companies\Models\Company;
use App\Core\Mailer\EmailBlockList;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\DebugContext;
use App\Sending\Email\Adapter\AwsAdapter;
use App\Sending\Email\Adapter\FailoverAdapter;
use App\Sending\Email\Adapter\InvoicedInboxAdapter;
use App\Sending\Email\Adapter\NullAdapter;
use App\Sending\Email\Adapter\SmtpAdapter;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Interfaces\AdapterInterface;
use App\Sending\Email\Interfaces\EmailInterface;
use App\Sending\Email\Models\SmtpAccount;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

class AdapterFactory implements LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    private const PROVIDER_SMTP = 'smtp';
    private const PROVIDER_NULL = 'null';

    public function __construct(
        private string $environment,
        private string $inboundEmailDomain,
        private AwsAdapter $awsAdapter,
        private InvoicedInboxAdapter $invoicedInboxAdapter,
        private CloudWatchLogsClient $cloudWatchLogsClient,
        private DebugContext $debugContext,
        private string $fallbackAdapters,
        private CacheStorage $storage,
        private LockFactory $lockFactory,
        private EmailBlockList $blockList,
    ) {
    }

    /**
     * Gets the sending adapter for a message.
     *
     * @throws SendEmailException
     */
    public function get(EmailInterface $message): AdapterInterface
    {
        // When sending to an Invoiced inbox we can just write
        // to our database directly and skip the email layer
        if ($this->isToInvoicedInbox($message)) {
            return $this->invoicedInboxAdapter;
        }

        $company = $message->getCompany();
        if (!$company->features->has('email_sending')) {
            throw new SendEmailException('Email sending is not enabled for your account. Please upgrade to use this feature.');
        }

        $emailProviderId = $company->accounts_receivable_settings->email_provider;
        if (self::PROVIDER_NULL == $emailProviderId) {
            return new NullAdapter();
        }

        if (self::PROVIDER_SMTP == $emailProviderId && $company->features->has('email_whitelabel')) {
            return $this->getSmtpAdapter($company);
        }

        return $this->getInvoicedAdapter();
    }

    /**
     * Checks if the message is being sent exclusively to an
     * Invoiced inbox.
     */
    private function isToInvoicedInbox(EmailInterface $message): bool
    {
        if (count($message->getCc()) || count($message->getBcc())) {
            return false;
        }

        $to = $message->getTo();
        if (1 != count($to)) {
            return false;
        }

        return str_ends_with($to[0]->getAddress(), $this->inboundEmailDomain);
    }

    /**
     * Gets the mail adapter used to send this message from the
     * Invoiced no-reply email.
     */
    private function getInvoicedAdapter(): AdapterInterface
    {
        if ('test' === $this->environment) {
            return new NullAdapter();
        }

        if ('dev' === $this->environment) {
            $smtpAccount = new SmtpAccount([
                'host' => 'mailhog',
                'username' => '',
                'password' => '',
                'port' => 1025,
            ]);

            return $this->makeSmtpAdapter($smtpAccount);
        }

        $adapters = [$this->awsAdapter];
        if ($this->fallbackAdapters) {
            $dsnList = json_decode($this->fallbackAdapters);
            foreach ($dsnList as $dsn) {
                $smtpAccount = SmtpAccount::fromDsn(Dsn::fromString($dsn));
                $adapters[] = $this->makeSmtpAdapter($smtpAccount);
            }
        }

        return $this->makeFailoverAdapter($adapters);
    }

    /**
     * Gets the mail adapter used to send this message using the
     * company's email infrastructure.
     *
     * @throws SendEmailException when the SMTP account cannot be found
     */
    private function getSmtpAdapter(Company $company): AdapterInterface
    {
        $smtpAccount = SmtpAccount::queryWithTenant($company)->oneOrNull();

        if (!($smtpAccount instanceof SmtpAccount)) {
            throw new SendEmailException('Your SMTP credentials have not been configured. This can be set up in Settings > Emails.');
        }

        $adapter = $this->makeSmtpAdapter($smtpAccount);

        // When enabled, failover to the Invoiced adapter upon failure
        if ($smtpAccount->fallback_on_failure) {
            return $this->makeFailoverAdapter([$adapter, $this->getInvoicedAdapter()]);
        }

        return $adapter;
    }

    private function makeSmtpAdapter(SmtpAccount $smtpAccount): SmtpAdapter
    {
        $adapter = new SmtpAdapter($smtpAccount, $this->cloudWatchLogsClient, $this->debugContext, $this->blockList, !$smtpAccount->persisted());
        $adapter->setLogger($this->logger);

        return $adapter;
    }

    private function makeFailoverAdapter(array $adapters): FailoverAdapter
    {
        $adapter = new FailoverAdapter($adapters, $this->storage, $this->lockFactory);
        $adapter->setStatsd($this->statsd);

        return $adapter;
    }
}
