<?php

namespace App\Core\Utils;

use App\Core\EnvironmentFacade;
use App\Core\Utils\Enums\ObjectType;

class AppUrl
{
    public function __construct(
        private string $protocol,
        private string $hostname,
        private int $port,
        private string $dashboardUrl,
    ) {
    }

    public static function get(): self
    {
        return new self(EnvironmentFacade::getAppProtocol(), EnvironmentFacade::getAppDomain(), EnvironmentFacade::getAppPort(), EnvironmentFacade::getDashboardUrl());
    }

    public function build(): string
    {
        return $this->protocol.'://'.$this->hostname.
            (!in_array($this->port, [0, 80, 443]) ? ':'.$this->port : '');
    }

    public function buildSubdomain(string $subdomain, bool $withProtocol = true): string
    {
        return ($withProtocol ? $this->protocol.'://' : '').
            $subdomain.'.'.$this->hostname.
            (!in_array($this->port, [0, 80, 443]) ? ':'.$this->port : '');
    }

    public function getProtocol(): string
    {
        return $this->protocol;
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Gets the link to an object in the application given a type and ID.
     */
    public function getObjectLink(ObjectType $objectType, mixed $id, array $query = []): ?string
    {
        return match ($objectType) {
            ObjectType::Bill => $this->generateDashboardUrl('/bills/'.$id, $query),
            ObjectType::Comment => $this->generateDashboardUrl('/'.$id.'/conversation', $query),
            ObjectType::Contact, ObjectType::Card, ObjectType::BankAccount, ObjectType::Customer => $this->generateDashboardUrl('/customers/'.$id, $query),
            ObjectType::CreditNote => $this->generateDashboardUrl('/credit_notes/'.$id, $query),
            ObjectType::DocumentView => $this->generateDashboardUrl('/'.$id, $query),
            ObjectType::EmailThread => $this->generateDashboardUrl('/inboxes/thread/'.$id, $query),
            ObjectType::Estimate => $this->generateDashboardUrl('/estimates/'.$id, $query),
            ObjectType::Import => $this->generateDashboardUrl('/imports/'.$id, $query),
            ObjectType::NetworkDocument => $this->generateDashboardUrl('/documents/'.$id, $query),
            ObjectType::Note, ObjectType::Task => $this->generateDashboardUrl('/customers/'.$id.'/collections', $query),
            ObjectType::PromiseToPay, ObjectType::PaymentPlan, ObjectType::Invoice => $this->generateDashboardUrl('/invoices/'.$id, $query),
            ObjectType::Refund, ObjectType::Charge, ObjectType::Payment => $this->generateDashboardUrl('/payments/'.$id, $query),
            ObjectType::Subscription => $this->generateDashboardUrl('/subscriptions/'.$id, $query),
            ObjectType::Transaction => $this->generateDashboardUrl('/transactions/'.$id, $query),
            ObjectType::Vendor => $this->generateDashboardUrl('/vendors/'.$id, $query),
            ObjectType::VendorCredit => $this->generateDashboardUrl('/vendor_credits/'.$id, $query),
            ObjectType::VendorPayment => $this->generateDashboardUrl('/vendor_payments/'.$id, $query),
            default => null,
        };
    }

    private function generateDashboardUrl(string $page, array $query): string
    {
        return $this->dashboardUrl.$page.(($query) ? '?'.http_build_query($query) : '');
    }
}
