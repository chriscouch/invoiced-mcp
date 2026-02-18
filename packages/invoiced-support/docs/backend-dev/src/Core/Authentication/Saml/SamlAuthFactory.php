<?php

namespace App\Core\Authentication\Saml;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Models\CompanySamlSettings;
use App\Core\Utils\AppUrl;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\Settings;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

/**
 * Builds instances of a SAML service provider authentication
 * server using the company's SAML configuration.
 */
class SamlAuthFactory implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const SETTINGS_BASE = [
        'strict' => true,
        'debug' => false,
        'contactPerson' => [
            'technical' => [
                'givenName' => 'Invoiced Support',
                'emailAddress' => 'support@invoiced.com',
            ],
            'support' => [
                'givenName' => 'Invoiced Support',
                'emailAddress' => 'support@invoiced.com',
            ],
        ],
        'organization' => [
            'en-US' => [
                'name' => 'Invoiced, Inc.',
                'displayname' => 'Invoiced, Inc.',
                'url' => 'https://invoiced.com',
            ],
        ],
        'sp' => [
            'entityId' => 'invoiced',
            'NameIDFormat' => Constants::NAMEID_EMAIL_ADDRESS,
        ],
    ];

    private const AZURE_DOMAIN = 'https://login.microsoftonline.com';

    private const PLACEHOLDER_CERT = '-----BEGIN CERTIFICATE-----
MIIHRzCCBi+gAwIBAgIQD6pjEJMHvD1BSJJkDM1NmjANBgkqhkiG9w0BAQsFADBP
MQswCQYDVQQGEwJVUzEVMBMGA1UEChMMRGlnaUNlcnQgSW5jMSkwJwYDVQQDEyBE
aWdpQ2VydCBUTFMgUlNBIFNIQTI1NiAyMDIwIENBMTAeFw0yMjAzMTQwMDAwMDBa
Fw0yMzAzMTQyMzU5NTlaMIGWMQswCQYDVQQGEwJVUzETMBEGA1UECBMKQ2FsaWZv
cm5pYTEUMBIGA1UEBxMLTG9zIEFuZ2VsZXMxQjBABgNVBAoMOUludGVybmV0wqBD
b3Jwb3JhdGlvbsKgZm9ywqBBc3NpZ25lZMKgTmFtZXPCoGFuZMKgTnVtYmVyczEY
MBYGA1UEAxMPd3d3LmV4YW1wbGUub3JnMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A
MIIBCgKCAQEAlV2WY5rlGn1fpwvuBhj0nVBcNxCxkHUG/pJG4HvaJen7YIZ1mLc7
/P4snOJZiEfwWFTikHNbcUCcYiKG8JkFebZOYMc1U9PiEtVWGU4kuYuxiXpD8oMP
in1B0SgrF7gKfO1//I2weJdAUjgZuXBCPAlhz2EnHddzXUtwm9XuOLO/Y6LATVMs
bp8/lXnfo/bX0UgJ7C0aVqOu07A0Vr6OkPxwWmOvF3cRKhVCM7U4B51KK+IsWRLm
8cVW1IaXjwhGzW7BR6EI3sxCQ4Wnc6HVPSgmomLWWWkIGFPAwcWUB4NC12yhCO5i
W/dxNMWNLMRVtnZAyq6FpZ8wFK6j4OMwMwIDAQABo4ID1TCCA9EwHwYDVR0jBBgw
FoAUt2ui6qiqhIx56rTaD5iyxZV2ufQwHQYDVR0OBBYEFPcqCdAkWxFx7rq+9D4c
PVYSiBa7MIGBBgNVHREEejB4gg93d3cuZXhhbXBsZS5vcmeCC2V4YW1wbGUubmV0
ggtleGFtcGxlLmVkdYILZXhhbXBsZS5jb22CC2V4YW1wbGUub3Jngg93d3cuZXhh
bXBsZS5jb22CD3d3dy5leGFtcGxlLmVkdYIPd3d3LmV4YW1wbGUubmV0MA4GA1Ud
DwEB/wQEAwIFoDAdBgNVHSUEFjAUBggrBgEFBQcDAQYIKwYBBQUHAwIwgY8GA1Ud
HwSBhzCBhDBAoD6gPIY6aHR0cDovL2NybDMuZGlnaWNlcnQuY29tL0RpZ2lDZXJ0
VExTUlNBU0hBMjU2MjAyMENBMS00LmNybDBAoD6gPIY6aHR0cDovL2NybDQuZGln
aWNlcnQuY29tL0RpZ2lDZXJ0VExTUlNBU0hBMjU2MjAyMENBMS00LmNybDA+BgNV
HSAENzA1MDMGBmeBDAECAjApMCcGCCsGAQUFBwIBFhtodHRwOi8vd3d3LmRpZ2lj
ZXJ0LmNvbS9DUFMwfwYIKwYBBQUHAQEEczBxMCQGCCsGAQUFBzABhhhodHRwOi8v
b2NzcC5kaWdpY2VydC5jb20wSQYIKwYBBQUHMAKGPWh0dHA6Ly9jYWNlcnRzLmRp
Z2ljZXJ0LmNvbS9EaWdpQ2VydFRMU1JTQVNIQTI1NjIwMjBDQTEtMS5jcnQwCQYD
VR0TBAIwADCCAXwGCisGAQQB1nkCBAIEggFsBIIBaAFmAHUA6D7Q2j71BjUy51co
vIlryQPTy9ERa+zraeF3fW0GvW4AAAF/ip6hdQAABAMARjBEAiAxePNT60Z/vTJT
PVryiGzXrLxCNJQqteULkguBEMbG/gIgR3QwvILJIWAUfvSfJQ/zMmqr2JDanWE8
uzbC4EWbcwAAdQA1zxkbv7FsV78PrUxtQsu7ticgJlHqP+Eq76gDwzvWTAAAAX+K
nqF8AAAEAwBGMEQCIDspTxwkUBpEoeA+IolNYwOKl9Yxmwk816yd0O2IJPZcAiAV
8TWhoOLiiqGKnY02CdcGXOzAzC7tT6m7OtLAku2+WAB2ALNzdwfhhFD4Y4bWBanc
EQlKeS2xZwwLh9zwAw55NqWaAAABf4qeoYcAAAQDAEcwRQIgKR7qwPLQb6UT2+S7
w7uQsbsDZfZVX/g8FkBtAltaTpACIQDLdtedRNGNhuzYpB6gmBBydhtSQi5YZLsp
FvaVHpeW1zANBgkqhkiG9w0BAQsFAAOCAQEAqp++XZEbreROTsyPB2RENbStOxM/
wSnYtKvzQlFJRjvWzx5Bg+ELVy+DaXllB29ZA4xRlIkYED4eXO26PY5PGhSS0yv/
1JjLp5MOvLcbk6RCQkbZ5bEaa2gqmy5IqS8dKrDj+CCUVIFQLu7X4CB6ey5n+/rY
F6Rb3MoAYu8jr3pY8Hp0DL1NQ/GMAofc464J0vf6NzzSS6sE5UOl0lURDkGHXzio
5XpeTEa4tvo/w0vNQDX/4KRxdArBIIvjVEeE1Ri9UZtAXd1CMBLROqVjmq+QCNYb
0XELBnGQ666tr7pfx9trHniitNEGI6dj87VD+laMUBd7HBtOEGsiDoRSlA==
-----END CERTIFICATE-----';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @throws AuthException
     */
    public function get(CompanySamlSettings $samlSettings): Auth
    {
        if (!$samlSettings->enabled) {
            throw new AuthException('Single sign-on is disabled');
        }

        $settings = $this->makeSettings($samlSettings);

        try {
            return new Auth($settings);
        } catch (Throwable $e) {
            $this->logger->warning('SSO settings error', [
                'settings' => $settings,
                'exception' => $e,
            ]);

            throw new AuthException($e->getMessage());
        }
    }

    /**
     * Gets the settings for the purpose of creating metadata.
     */
    public function getMetadataSettings(string $domain): Settings
    {
        $samlSettings = CompanySamlSettings::where('domain', $domain)->oneOrNull() ?? new CompanySamlSettings(['domain' => $domain]);

        return new Settings($this->makeSettings($samlSettings));
    }

    private function makeSettings(CompanySamlSettings $samlSettings): array
    {
        $settings = self::SETTINGS_BASE;

        $settings['baseurl'] = AppUrl::get()->build().'/auth/sso/sp';

        $settings['sp']['assertionConsumerService'] = [
            'url' => $this->urlGenerator->generate('sso_acs', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
        if ($samlSettings->domain) {
            $settings['sp']['singleLogoutService'] = [
                'url' => $this->urlGenerator->generate('sso_logout', ['domain' => $samlSettings->domain], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        }

        $settings['idp'] = [
            'entityId' => $samlSettings->entity_id ?: 'placeholder',
            'singleSignOnService' => [
                'url' => $samlSettings->sso_url ?: 'https://example.com',
                'binding' => Constants::BINDING_HTTP_REDIRECT,
            ],
            'singleLogoutService' => [
                'url' => $samlSettings->slo_url ?: 'https://example.com',
                'responseUrl' => '',
                'binding' => Constants::BINDING_HTTP_REDIRECT,
            ],
            'x509cert' => $samlSettings->cert ?: self::PLACEHOLDER_CERT,
        ];

        // Use different settings for Azure IdP
        if (str_starts_with($settings['idp']['singleSignOnService']['url'], self::AZURE_DOMAIN)) {
            $settings['strict'] = false;
            $settings['idp']['singleSignOnService']['binding'] = Constants::BINDING_HTTP_POST;
            $settings['idp']['singleLogoutService']['binding'] = Constants::BINDING_HTTP_POST;
        }

        return $settings;
    }
}
