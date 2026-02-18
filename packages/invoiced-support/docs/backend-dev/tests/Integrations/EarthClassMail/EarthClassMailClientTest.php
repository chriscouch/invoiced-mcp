<?php

namespace App\Tests\Integrations\EarthClassMail;

use App\Core\Utils\DebugContext;
use App\Integrations\EarthClassMail\EarthClassMailClient;
use App\Integrations\EarthClassMail\Models\EarthClassMailAccount;
use App\Integrations\EarthClassMail\ValueObjects\Check;
use App\Integrations\EarthClassMail\ValueObjects\Piece;
use App\Tests\AppTestCase;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Carbon\CarbonImmutable;
use Mockery;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EarthClassMailClientTest extends AppTestCase
{
    private static string $jsonDIR = __DIR__.'/json/earth_class_mail_client';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    private function getClient(HttpClientInterface $client): EarthClassMailClient
    {
        return new EarthClassMailClient($client, Mockery::mock(CloudWatchLogsClient::class), new DebugContext('test'));
    }

    public function testGetDeposits(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/earth_class_mail_client_response_1.json')),
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/earth_class_mail_client_response_media.json')),
        ]);
        $ecmClient = $this->getClient($client);

        $account = new EarthClassMailAccount();
        $account->api_key = 'key';

        $check = new Check(20000, '2', '1');
        $data = new Piece('2020-03-06T18:01:31Z');
        $data->checks = [$check];
        $data->addMedia('https://invoiced2', 'image', [
            'test1',
        ]);
        $data->addMedia('https://invoiced6', 'image', [
            'test2',
        ]);
        $data->addMedia('https://invoiced5', 'image', [
            'test3',
        ]);
        $data->addMedia('https://invoiced7', 'image', []);
        $data->addMedia('https://invoiced8', 'image', []);
        $data->addMedia('https://invoiced9', 'image', [
            'test1',
            'test2',
        ]);

        $inboxId = 123;
        $dateFrom = new CarbonImmutable('-1 day');

        $deposits = $ecmClient->getDeposits($account, $inboxId, $dateFrom);
        $this->assertArrayHasKey(111, $deposits);
        $this->assertCount(1, $deposits);
        $deposit = $deposits[111];
        $this->assertEquals('2020-03-06T18:01:31Z', $deposit->created_at);
        $this->assertEquals([$check], $deposit->checks);
        $this->assertEquals(array_values($data->getMedia()), array_values($deposit->getMedia()));
        $this->assertFalse($ecmClient->hasMoreDeposits());
    }

    public function testGetDepositsMultiPage(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/earth_class_mail_client_response_2.json')),
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/earth_class_mail_client_response_media.json')),
        ]);
        $ecmClient = $this->getClient($client);

        $account = new EarthClassMailAccount();
        $account->api_key = 'key';

        $inboxId = 123;
        $dateFrom = new CarbonImmutable('-1 day');

        $ecmClient->getDeposits($account, $inboxId, $dateFrom);

        $this->assertTrue($ecmClient->hasMoreDeposits());
    }
}
