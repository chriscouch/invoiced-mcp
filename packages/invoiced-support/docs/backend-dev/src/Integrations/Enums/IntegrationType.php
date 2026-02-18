<?php

namespace App\Integrations\Enums;

use App\Integrations\Exceptions\IntegrationException;

enum IntegrationType: int
{
    case Intacct = 1;
    case NetSuite = 2;
    case QuickBooksDesktop = 3;
    case QuickBooksOnline = 4;
    case Xero = 5;
    case Avalara = 7;
    case Lob = 8;
    case Slack = 9;
    case Twilio = 10;
    case EarthClassMail = 11;
    case ChartMogul = 12;
    case Custom = 13;
    case BusinessCentral = 14;
    case Wave = 15;
    case FreshBooks = 16;
    case SageAccounting = 17;
    case HubSpot = 18;
    case ServiceNow = 19;

    private const ACCOUNTING_INTEGRATIONS = [
        self::BusinessCentral,
        self::FreshBooks,
        self::Intacct,
        self::NetSuite,
        self::QuickBooksDesktop,
        self::QuickBooksOnline,
        self::SageAccounting,
        self::Wave,
        self::Xero,
    ];

    public function toString(): string
    {
        return match ($this) {
            self::Avalara => 'avalara',
            self::BusinessCentral => 'business_central',
            self::ChartMogul => 'chartmogul',
            self::Custom => 'custom',
            self::EarthClassMail => 'earth_class_mail',
            self::FreshBooks => 'freshbooks',
            self::HubSpot => 'hubspot',
            self::Intacct => 'intacct',
            self::Lob => 'lob',
            self::NetSuite => 'netsuite',
            self::QuickBooksDesktop => 'quickbooks_desktop',
            self::QuickBooksOnline => 'quickbooks_online',
            self::SageAccounting => 'sage_accounting',
            self::ServiceNow => 'service_now',
            self::Slack => 'slack',
            self::Twilio => 'twilio',
            self::Wave => 'wave',
            self::Xero => 'xero',
        };
    }

    public function toHumanName(): string
    {
        return match ($this) {
            self::Avalara => 'Avalara',
            self::BusinessCentral => 'Business Central',
            self::ChartMogul => 'ChartMogul',
            self::Custom => 'Custom',
            self::EarthClassMail => 'Earth Class Mail',
            self::FreshBooks => 'FreshBooks',
            self::HubSpot => 'HubSpot',
            self::Intacct => 'Intacct',
            self::Lob => 'Lob',
            self::NetSuite => 'NetSuite',
            self::QuickBooksDesktop => 'QuickBooks Desktop',
            self::QuickBooksOnline => 'QuickBooks Online',
            self::SageAccounting => 'Sage Accounting',
            self::ServiceNow => 'ServiceNow',
            self::Slack => 'Slack',
            self::Twilio => 'Twilio',
            self::Wave => 'Wave',
            self::Xero => 'Xero',
        };
    }

    public static function fromString(string $stringId): self
    {
        foreach (self::cases() as $case) {
            if ($case->toString() == $stringId) {
                return $case;
            }
        }

        throw new IntegrationException('No such integration: '.$stringId);
    }

    /**
     * Gets all the integration types which are accounting integrations.
     *
     * @return IntegrationType[]
     */
    public static function accountingIntegrations(): array
    {
        return self::ACCOUNTING_INTEGRATIONS;
    }
}
