<?php

namespace App\EntryPoint\CronJob;

use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class SyncLeadsToHubSpot implements CronJobInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const COUNTRIES = [
        'CA' => 'Canada',
        'US' => 'United States',
    ];

    public function __construct(
        private Connection $connection,
        private HttpClientInterface $httpClient,
        private string $hubspotKey,
        private string $environment,
        private string $projectDir,
    ) {
    }

    public static function getName(): string
    {
        return 'sync_leads_to_hubspot';
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function execute(Run $run): void
    {
        if ('production' != $this->environment) {
            return;
        }

        $run->writeOutput('Creating new sign ups as contacts on HubSpot');
        $n = 0;

        try {
            foreach ($this->getData() as $lead) {
                if ($this->createContact($lead)) {
                    ++$n;
                }
            }
        } catch (Throwable $e) {
            $this->logger->error('Adding leads to HubSpot failed', ['exception' => $e]);
        }

        $run->writeOutput("$n contacts created");
    }

    private function getData(): array
    {
        return $this->connection->fetchAllAssociative('SELECT u.first_name,
       u.last_name,
       c.name    AS company_name,
       u.email,
       c.phone,
       c.website,
       c.address1,
       c.address2,
       c.city,
       c.state,
       c.postal_code,
       c.country,
       IF((SELECT COUNT(*) FROM InstalledProducts i JOIN ProductFeatures f ON f.product_id=i.product_id WHERE i.tenant_id = c.id AND f.feature="accounts_receivable") > 0, "Accounts Receivable", "Accounts Payable") AS demo_type,
       c.id      AS tenant_id,
       c.created_at,
       a.utm_source,
       a.utm_campaign,
       a.utm_medium,
       a.utm_term,
       a.utm_content
FROM Companies c
         JOIN Users u ON u.id = c.creator_id
         JOIN BillingProfiles b ON b.id = c.billing_profile_id
         LEFT JOIN MarketingAttributions a ON a.tenant_id = c.id
WHERE c.creator_id IS NOT NULL
  AND c.fraud = 0
  AND b.billing_system IS NULL
  AND c.type = "company"
  AND c.country IN ("US", "CA")
  AND c.created_at >= :startDate
  AND c.canceled = 0
  AND NOT EXISTS(SELECT 1 FROM Features WHERE feature = "needs_onboarding" AND enabled = 1 AND tenant_id = c.id)', [
          'startDate' => CarbonImmutable::now()->subDays(2)->toDateTimeString(),
        ]);
    }

    private function createContact(array $data): ?array
    {
        // Check if a personal email domain
        $exactMatches = json_decode((string) file_get_contents($this->projectDir.'/config/personalEmails/index.json'));
        [, $domain] = explode('@', $data['email']);
        foreach ($exactMatches as $match) {
            if ($domain == $match) {
                return null;
            }
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.hubapi.com/crm/v3/objects/contacts', [
                'auth_bearer' => $this->hubspotKey,
                'json' => [
                    'properties' => [
                        'company' => $data['company_name'],
                        'email' => $data['email'],
                        'firstname' => $data['first_name'],
                        'lastname' => $data['last_name'],
                        'address' => trim($data['address1'].' '.$data['address2']),
                        'city' => $data['city'],
                        'state' => $data['state'],
                        'zip' => $data['postal_code'],
                        'country' => self::COUNTRIES[$data['country']],
                        'website' => $data['website'],
                        'phone' => $data['phone'],
                        'utm_campaign' => 'Sign up',
                        'utm_content' => $data['utm_content'],
                        'utm_medium' => $data['utm_medium'],
                        'utm_source' => $data['utm_source'],
                        'utm_term' => $data['utm_term'],
                        'hs_analytics_source' => 'OTHER_CAMPAIGNS',
                        'lifecyclestage' => 'lead',
                        'demo_type' => $data['demo_type'],
                        'tenant_id' => $data['tenant_id'],
                    ],
                ],
            ]);

            return $response->toArray();
        } catch (Throwable $e) {
            // Ignore 409 conflict which means the contact already exists
            if ($e instanceof ClientException && 409 == $e->getResponse()->getStatusCode()) {
                return null;
            }

            throw $e;
        }
    }
}
