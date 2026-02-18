<?php

namespace App\Companies\FraudScore;

use App\Companies\Interfaces\FraudScoreInterface;
use App\Companies\ValueObjects\FraudEvaluationState;
use App\Core\I18n\Countries;
use App\Core\Utils\IpLookup;
use App\Core\Utils\IpUtilities;
use Doctrine\DBAL\Connection;

/**
 * Scores the fraud likelihood of a sign up based on its IP address.
 */
class IpAddressFraudScore implements FraudScoreInterface
{
    public function __construct(
        private IpLookup $ipLookup,
        private Connection $databsae,
    ) {
    }

    public function calculateScore(FraudEvaluationState $state): int
    {
        $score = 0;
        $ip = $state->requestParams['ip'] ?? '';

        $n = IpUtilities::isBlocked($ip, $this->databsae);
        if ($n > 0) {
            $state->addLine('IP address ('.$ip.') is on our block list');

            // a previously fraudulent IP is an automatic ban
            $score += $state->blockScoreThreshold * $n + 1;
        }

        $ipInfo = $this->ipLookup->get($ip);
        if (!$ipInfo) {
            return $score;
        }
        $state->addLine('IP info: '.json_encode($ipInfo));

        // Check if the IP address is a VPN, hosting service, or Tor.
        if (isset($ipInfo->privacy)) {
            if ($ipInfo->privacy->vpn && !isset($state->requestParams['allow_vpn'])) {
                $state->addLine('IP address is detected as a VPN');
                $score += $state->blockScoreThreshold + 1;
            }

            if ($ipInfo->privacy->hosting && !isset($state->requestParams['allow_hosting'])) {
                $state->addLine('IP address is detected as a hosting service');
                $score += $state->blockScoreThreshold + 1;
            }

            if ($ipInfo->privacy->tor) {
                $state->addLine('IP address is detected as coming from Tor');
                $score += $state->blockScoreThreshold + 1;
            }
        }

        // Checks that the creator's IP matches the company IP.
        $companyCountry = $state->companyParams['country'] ?? '';
        if ($companyCountry && isset($ipInfo->country) && strtolower($ipInfo->country) != strtolower($companyCountry)) {
            $countries = new Countries();
            $country = $countries->get($ipInfo->country);
            $ipCountryName = $country ? $country['country'] : null;
            $country = $countries->get($companyCountry);
            $companyCountryName = $country ? $country['country'] : null;
            $state->addLine('IP country ('.$ipCountryName.') does not match company country ('.$companyCountryName.')');
            $score += 2;
            // Mismatched country IP coming from Nigeria is instaban because it is a common occurrence
            if ('NG' == $ipInfo->country) {
                $score += $state->blockScoreThreshold;
            }
        }

        return $score;
    }
}
