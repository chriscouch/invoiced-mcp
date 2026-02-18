<?php

namespace App\Core\Authentication\Traits;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use Jenssegers\Agent\Agent;
use Throwable;

trait ChangeAgentHelperTrait
{
    protected function getIpInfo(string $ip): array
    {
        $location = 'Unknown';
        $timezone = 'America/Chicago';
        if ($ipInfo = $this->ipLookup->get($ip)) {
            $city = $ipInfo->city ?? '';
            $region = $ipInfo->region ?? '';
            $country = $ipInfo->country ?? '';
            $country = 'US' != $country ? $country : null;
            $location = implode(', ', array_filter([$city, $region, $country]));
            $location = $location ?: 'Unknown';
            $timezone = $ipInfo->timezone ?? $timezone;
        }

        return [
            'location' => $location,
            'timezone' => $timezone,
        ];
    }

    protected function getUserAgentDescription(string $userAgent): string
    {
        $agent = new Agent();
        $agent->setUserAgent($userAgent);

        return $agent->device().', '.$agent->platform().', '.$agent->browser();
    }

    protected function getTimestamp(string $timezone): string
    {
        $dateFormat = 'l, F jS, Y \a\t g:i:sa T';
        try {
            return CarbonImmutable::now(new CarbonTimeZone($timezone))->format($dateFormat);
        } catch (Throwable) {
            return date($dateFormat);
        }
    }
}
