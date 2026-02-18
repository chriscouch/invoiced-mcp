<?php

namespace App\Companies\Verification;

use App\Companies\Exception\BusinessVerificationException;
use App\Core\Utils\LoggerFactory;
use CommerceGuys\Addressing\Address;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

class AddressVerification implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    // Address validation is less reliable in these countries. Many seemingly
    // valid addresses only have an OTHER granularity.
    private const FLAKY_COUNTRIES = [
        'BR',
        'IE',
    ];

    private LoggerInterface $responseLogger;

    public function __construct(
        private string $googleApiKey,
        private HttpClientInterface $httpClient,
        private LoggerFactory $loggerFactory,
    ) {
    }

    public function countryIsSupported(string $country): bool
    {
        return in_array($country, [
            'AU',
            'AT',
            'BE',
            'BR',
            'CA',
            'CL',
            'CO',
            'CZ',
            'DK',
            'EE',
            'FI',
            'FR',
            'DE',
            'HU',
            'IE',
            'IT',
            'LV',
            'LT',
            'LU',
            'MY',
            'MX',
            'NL',
            'NO',
            'NZ',
            'PL',
            'PR',
            'SG',
            'SK',
            'SI',
            'ES',
            'SE',
            'CH',
            'GB',
            'US',
        ]);
    }

    /**
     * @throws BusinessVerificationException
     */
    public function validate(Address $address): void
    {
        if (!$this->countryIsSupported($address->getCountryCode())) {
            return;
        }

        $addressRequest = [
            'regionCode' => $address->getCountryCode(),
            'addressLines' => [],
        ];

        if ($address1 = $address->getAddressLine1()) {
            $addressRequest['addressLines'][] = $address1;
        }

        if ($address2 = $address->getAddressLine2()) {
            $addressRequest['addressLines'][] = $address2;
        }

        if ($postalCode = $address->getPostalCode()) {
            $addressRequest['postalCode'] = $postalCode;
        }

        if ($sortingCode = $address->getSortingCode()) {
            $addressRequest['sortingCode'] = $sortingCode;
        }

        if ($administrativeArea = $address->getAdministrativeArea()) {
            $addressRequest['administrativeArea'] = $administrativeArea;
        }

        if ($locality = $address->getLocality()) {
            $addressRequest['locality'] = $locality;
        }

        try {
            // TODO: retries and rate limiting
            $response = $this->httpClient->request(
                'POST',
                'https://addressvalidation.googleapis.com/v1:validateAddress',
                [
                    'query' => [
                        'key' => $this->googleApiKey,
                    ],
                    'json' => [
                        'address' => $addressRequest,
                    ],
                ],
            );

            $result = json_decode($response->getContent())->result;
            $this->logResponse($response);
            $this->checkResponse($result, $address);
        } catch (ExceptionInterface $e) {
            // log the response
            if ($e instanceof HttpExceptionInterface) {
                $response = $e->getResponse();
                $this->logResponse($response);

                if (400 == $response->getStatusCode()) {
                    $result = json_decode($response->getContent(false));

                    throw new BusinessVerificationException($result->error->message);
                }
            }

            // log the exception
            $this->logger->error('Unexpected failure when validating physical address', ['exception' => $e]);

            throw new BusinessVerificationException('We were unable to validate the given address.');
        }
    }

    private function logResponse(ResponseInterface $response): void
    {
        if (!isset($this->responseLogger)) {
            $this->responseLogger = $this->loggerFactory->get('fraud');
        }

        try {
            $result = $response->toArray(false);
            $this->responseLogger->log('info', 'Response from Google Address Validation: '.json_encode($result));
        } catch (Throwable) {
            // ignore
        }
    }

    private function checkResponse(stdClass $result, Address $address): void
    {
        $allowedGranularity = ['SUB_PREMISE', 'PREMISE'];

        // Address validation is less reliable in some countries.
        if (in_array($address->getCountryCode(), self::FLAKY_COUNTRIES)) {
            $allowedGranularity[] = 'OTHER';
        }

        // A valid address is validated at the sub-premise or premise granularity
        if (!in_array($result->verdict->validationGranularity, $allowedGranularity)) {
            throw new BusinessVerificationException('We were unable to validate the given address.');
        }

        // If any component has been replaced then the address is not valid
        if ($result->verdict->hasReplacedComponents ?? false) {
            throw new BusinessVerificationException('We were unable to validate the given address.');
        }
    }
}
