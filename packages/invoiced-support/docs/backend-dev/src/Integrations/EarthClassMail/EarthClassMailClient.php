<?php

namespace App\Integrations\EarthClassMail;

use App\Core\Utils\DebugContext;
use App\Integrations\EarthClassMail\Models\EarthClassMailAccount;
use App\Integrations\EarthClassMail\ValueObjects\Check;
use App\Integrations\EarthClassMail\ValueObjects\Piece;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Traits\IntegrationLogAwareTrait;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Carbon\CarbonImmutable;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EarthClassMailClient
{
    use IntegrationLogAwareTrait;

    private const BASE_URI = 'https://api.earthclassmail.com';

    private bool $hasMoreDeposits;

    public function __construct(
        private HttpClientInterface $httpClient,
        private CloudWatchLogsClient $cloudWatchLogsClient,
        private DebugContext $debugContext,
    ) {
    }

    public function hasMoreDeposits(): bool
    {
        return $this->hasMoreDeposits;
    }

    /**
     * Gets a list of inboxes.
     *
     * @throws IntegrationApiException
     */
    public function getInboxes(EarthClassMailAccount $account): array
    {
        $response = $this->performGet($account, '/v1/inboxes', ['payload_type' => '1']);

        $inboxes = [];
        foreach ($response->data as $inbox) {
            $inboxWithAccount = $this->performGet($account, '/v1/inboxes/'.$inbox->id);
            $inboxes[] = [
                'id' => $inbox->id,
                'name' => $inboxWithAccount->account->name.' (#'.$inbox->id.')',
            ];
        }

        return $inboxes;
    }

    /**
     * Gets a list of check deposits.
     *
     * @throws IntegrationApiException
     *
     * @return Piece[]
     */
    public function getDeposits(EarthClassMailAccount $account, int $inboxId, ?CarbonImmutable $dateFrom, int $page = 1): array
    {
        $requestUrl = '/v1/inboxes/'.$inboxId.'/check-deposit-requests';

        $parameters = [
            'has_checks' => '1',
            'inbox_id' => $inboxId,
            'sort_by' => 'requested_at',
            'sort_direction' => 'desc',
            'page' => (string) $page,
            'per_page' => '25',
        ];
        if ($dateFrom) {
            $parameters['date_from'] = $dateFrom->format('Y-m-d\TH:m:s\Z');
        }

        $result = $this->performGet($account, $requestUrl, $parameters);
        $pieces = [];
        foreach ($result->data as $data) {
            $pieces[$data->piece_id] = new Piece($data->created_at);
            foreach ($data->checks as $check) {
                if (property_exists($check, 'media')) {
                    foreach ($check->media as $media) {
                        $pieces[$data->piece_id]->addMedia(
                            $media->url,
                            $media->content_type,
                            $media->tags,
                        );
                    }
                }
                $pieces[$data->piece_id]->checks[] = new Check(
                    $check->amount_in_cents,
                    $check->check_number,
                    $check->id
                );
            }
        }

        if (!$pieces) {
            $this->hasMoreDeposits = false;

            return [];
        }

        $requestUrl = '/v1/media?piece_id='.implode(',', array_keys($pieces));
        $result2 = $this->performGet($account, $requestUrl);
        foreach ($result2->data as $mediaItem) {
            if (!$pieces[$mediaItem->piece_id]) { /* @phpstan-ignore-line */
                continue;
            }
            $pieces[$mediaItem->piece_id]->addMedia(
                $mediaItem->url,
                $mediaItem->content_type,
                $mediaItem->tags ?? [],
            );
        }

        $this->hasMoreDeposits = ($result->last_page > 0) && ($result->current_page != $result->last_page);

        return $pieces;
    }

    /**
     * Performs a GET HTTP request.
     *
     * @throws IntegrationApiException
     */
    private function performGet(EarthClassMailAccount $account, string $endpoint, array $queryParameters = []): object
    {
        try {
            $response = $this->getHttpClient($account)->request('GET', $endpoint, [
                'base_uri' => self::BASE_URI,
                'headers' => [
                    'x-api-key' => $account->api_key,
                ],
                'query' => $queryParameters,
            ]);

            return json_decode($response->getContent());
        } catch (ExceptionInterface $e) {
            if ($e instanceof HttpExceptionInterface) {
                $response = $e->getResponse();
                if (401 == $response->getStatusCode()) {
                    throw new IntegrationApiException('Could not connect to Earth Class Mail due to an invalid API key.', 401, $e);
                }
            }

            throw new IntegrationApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function getHttpClient(EarthClassMailAccount $account): HttpClientInterface
    {
        if (!isset($this->loggingHttpClient)) {
            $this->loggingHttpClient = $this->makeSymfonyLogger('earth_class_mail', $account->tenant(), $this->cloudWatchLogsClient, $this->debugContext, $this->httpClient);
        }

        return $this->loggingHttpClient;
    }
}
