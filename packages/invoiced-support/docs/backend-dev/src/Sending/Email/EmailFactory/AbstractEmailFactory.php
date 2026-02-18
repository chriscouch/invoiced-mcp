<?php

namespace App\Sending\Email\EmailFactory;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Models\Inbox;
use App\Sending\Email\Models\InboxDecorator;
use App\Sending\Email\ValueObjects\NamedAddress;

abstract class AbstractEmailFactory
{
    const MAX_RECIPIENTS = 5;

    public function __construct(protected string $inboundEmailDomain)
    {
    }

    /**
     * @throws SendEmailException
     *
     * @return NamedAddress[]
     */
    public function generateTo(array $to, Company $company, ?Customer $customer = null): array
    {
        /** @var NamedAddress[] $result */
        $result = [];
        foreach ($to as $contact) {
            // prevent adding any duplicate recipients
            // WARNING this is O(N^2)
            $duplicate = false;
            foreach ($result as $contact2) {
                if (trim(strtolower($contact2->getAddress())) == trim(strtolower($contact['email']))) {
                    $duplicate = true;

                    break;
                }
            }

            if ($duplicate) {
                continue;
            }

            // prevent adding more than N recipients
            if (!$company->features->has('unlimited_recipients') && count($result) >= self::MAX_RECIPIENTS) {
                throw new SendEmailException('Cannot send to more than '.self::MAX_RECIPIENTS.' recipients at a time.');
            }

            if (!$this->validateEmailAddress($contact['email'])) {
                throw new SendEmailException("Invalid email address: {$contact['email']}");
            }

            if (!isset($contact['name'])) {
                $contact['name'] = $customer ? $customer->name : '';
            }

            $result[] = new NamedAddress($contact['email'], $contact['name']);
        }

        return $result;
    }

    /**
     * @param NamedAddress[] $to
     *
     * @return NamedAddress[]
     */
    public function generateBccs(Company $company, array $to, ?string $bcc): array
    {
        if (null === $bcc) {
            $bcc = $company->accounts_receivable_settings->bcc;
        }
        $bccArray = [];

        if ($bcc) {
            $bccArray = array_map(fn ($email) => [
                'email' => $email,
                'name' => $company->getDisplayName(),
            ], explode(',', $bcc));
        }

        return $this->generateCc($to, $bccArray);
    }

    /**
     * @param NamedAddress[] $exclude
     *
     * @return NamedAddress[]
     */
    public function generateCc(array $exclude, array $cc): array
    {
        // unique
        $unique = [];
        foreach ($cc as $ccItem) {
            $addr = trim(strtolower($ccItem['email']));
            if ($this->validateEmailAddress($addr)) {
                $unique[$addr] = $ccItem['name'];
            }
        }
        // normalize
        $ccs = [];
        foreach ($unique as $key => $value) {
            $ccs[] = new NamedAddress($key, $value);
        }

        // exclude already sent
        $ccs = array_udiff($ccs, $exclude, fn (NamedAddress $a, NamedAddress $b) => $a->getAddress() <=> $b->getAddress());

        // can only add up to 5 recipients
        return array_slice($ccs, 0, self::MAX_RECIPIENTS);
    }

    /**
     * Validates an email address.
     */
    public function validateEmailAddress(string $email): bool
    {
        $email = trim(strtolower($email));

        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Generates the default set of template variables.
     */
    protected function buildDefaultTemplateVars(Company $company): array
    {
        $nameWithAddress = $company->address(true, true);
        $nameWithAddress = str_replace("\n", ', ', trim($nameWithAddress));

        return [
            'highlightColor' => $company->highlight_color,
            'logo' => $company->logo,
            'companyName' => $company->getDisplayName(),
            'companyNameWithAddress' => $nameWithAddress,
            'testMode' => $company->test_mode,
            'showPoweredBy' => $company->features->has('powered_by'),
        ];
    }

    /**
     * Generates any message headers.
     */
    protected function generateHeaders(Company $company, string $emailId, ?Inbox $inbox): array
    {
        if ($inbox) {
            $decorator = new InboxDecorator($inbox, $this->inboundEmailDomain);
            $replyTo = (string) $decorator->getNamedEmailAddress();
        } else {
            $replyTo = (string) (new NamedAddress((string) $company->email, $company->getDisplayName()));
        }

        return [
            'X-Invoiced' => 'true',
            'X-Invoiced-Account' => $company->identifier,
            // When enabled this lets email systems know to not send an auto reply
            // email in response to our emails. This keeps message threads clean.
            'X-Auto-Response-Suppress' => 'All',
            'Message-ID' => '<'.$company->username.'/'.$emailId.'@invoiced.com>',
            'Reply-To' => $replyTo,
        ];
    }

    protected function stripPlainTextTags(string $input): string
    {
        return (string) preg_replace('/(<plainTextOnly>[^<>]*<\\/plainTextOnly>)/', '', $input);
    }
}
