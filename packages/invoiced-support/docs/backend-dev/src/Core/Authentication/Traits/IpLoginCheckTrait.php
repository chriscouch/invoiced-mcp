<?php

namespace App\Core\Authentication\Traits;

use App\Companies\ValueObjects\FraudEvaluationState;
use App\Core\Authentication\Exception\AuthException;
use Symfony\Component\HttpFoundation\Request;

trait IpLoginCheckTrait
{
    /**
     * Check if the IP address is allowed to sign in.
     */
    private function checkIpAddress(Request $request): void
    {
        $requestParams = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'allow_vpn' => true,
            'allow_hosting' => true,
        ];
        $state = new FraudEvaluationState(requestParams: $requestParams);
        $score = $this->ipAddressFraudScore->calculateScore($state);
        if ($score > $state->blockScoreThreshold) {
            $this->statsd->increment('security.login_ip_block', 1.0, ['strategy' => $this->getId()]);

            throw new AuthException('Signing in to Invoiced with a VPN is not allowed.');
        }
    }
}
