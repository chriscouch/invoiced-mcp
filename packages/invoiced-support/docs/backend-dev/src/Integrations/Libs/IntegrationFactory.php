<?php

namespace App\Integrations\Libs;

use App\Companies\Models\Company;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Interfaces\IntegrationInterface;
use App\Integrations\Services\Avalara;
use App\Integrations\Services\BusinessCentral;
use App\Integrations\Services\ChartMogul;
use App\Integrations\Services\EarthClassMail;
use App\Integrations\Services\FreshBooks;
use App\Integrations\Services\Intacct;
use App\Integrations\Services\Lob;
use App\Integrations\Services\Netsuite;
use App\Integrations\Services\QuickbooksDesktop;
use App\Integrations\Services\QuickbooksOnline;
use App\Integrations\Services\SageAccounting;
use App\Integrations\Services\Slack;
use App\Integrations\Services\Twilio;
use App\Integrations\Services\Wave;
use App\Integrations\Services\Xero;

class IntegrationFactory
{
    private array $supportedIntegrations;

    public function __construct()
    {
        $this->supportedIntegrations = [
            IntegrationType::Avalara->value => Avalara::class,
            IntegrationType::BusinessCentral->value => BusinessCentral::class,
            IntegrationType::ChartMogul->value => ChartMogul::class,
            IntegrationType::EarthClassMail->value => EarthClassMail::class,
            IntegrationType::FreshBooks->value => FreshBooks::class,
            IntegrationType::Intacct->value => Intacct::class,
            IntegrationType::Lob->value => Lob::class,
            IntegrationType::NetSuite->value => Netsuite::class,
            IntegrationType::QuickBooksDesktop->value => QuickbooksDesktop::class,
            IntegrationType::QuickBooksOnline->value => QuickbooksOnline::class,
            IntegrationType::SageAccounting->value => SageAccounting::class,
            IntegrationType::Slack->value => Slack::class,
            IntegrationType::Twilio->value => Twilio::class,
            IntegrationType::Wave->value => Wave::class,
            IntegrationType::Xero->value => Xero::class,
        ];
    }

    /**
     * Gets an instance of an integration by the numeric ID.
     *
     * @throws IntegrationException if the integration does not exist
     */
    public function get(IntegrationType $integrationType, Company $tenant): IntegrationInterface
    {
        if (!isset($this->supportedIntegrations[$integrationType->value])) {
            throw new IntegrationException('Integration does not exist: '.$integrationType->toString());
        }

        $class = $this->supportedIntegrations[$integrationType->value];

        return new $class($tenant, $integrationType); /* @phpstan-ignore-line */
    }

    /**
     * Gets an instance of every possible integration.
     *
     * @return IntegrationInterface[]
     */
    public function all(Company $company): array
    {
        $result = [];
        foreach (IntegrationType::cases() as $integrationType) {
            if (isset($this->supportedIntegrations[$integrationType->value])) {
                $result[$integrationType->toString()] = $this->get($integrationType, $company);
            }
        }

        return $result;
    }
}
