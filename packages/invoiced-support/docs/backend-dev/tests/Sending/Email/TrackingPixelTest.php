<?php

namespace App\Tests\Sending\Email;

use App\Core\Authentication\Models\User;
use App\Core\Utils\InfuseUtility as Utility;
use App\Sending\Email\Libs\EmailOpenTracker;
use App\Sending\Email\ValueObjects\TrackingPixel;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Response;

class TrackingPixelTest extends AppTestCase
{
    private static User $ogUser;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$ogUser = self::getService('test.user_context')->get();
        self::hasCompany();
        self::hasInbox();
        self::hasEmailThread();
        self::hasInboxEmail(Utility::guid(false));
    }

    public function assertPostConditions(): void
    {
        parent::assertPostConditions();
        self::getService('test.user_context')->set(self::$ogUser);
    }

    public function testGetId(): void
    {
        $pixel = new TrackingPixel('test');
        $this->assertEquals('test', $pixel->getId());

        $pixel = new TrackingPixel();
        $this->assertEquals(32, strlen($pixel->getId()));
    }

    public function testBuildHtml(): void
    {
        $expected = '<img src="http://invoiced.localhost:1234/email/open/'.self::$inboxEmail->tracking_id.'" alt="" width="1" height="1" border="0" style="height:1px !important;width:1px !important;border-width:0 !important;margin-top:0 !important;margin-bottom:0 !important;margin-right:0 !important;margin-left:0 !important;padding-top:0 !important;padding-bottom:0 !important;padding-right:0  !important;padding-left:0 !important;" />';
        $this->assertEquals($expected, $this->getPixel()->__toString());
    }

    public function testRecordOpen(): void
    {
        $tracker = $this->getTracker();
        $pixel = $this->getPixel();

        $tracker->recordOpen($pixel);
        $this->assertEquals(0, self::$inboxEmail->refresh()->opens);

        self::getService('test.user_context')->set(new User(['id' => -1]));
        $tracker->recordOpen($pixel);
        $this->assertEquals(1, self::$inboxEmail->refresh()->opens);
    }

    public function testBuildResponse(): void
    {
        $response = $this->getPixel()->buildResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('image/gif', $response->headers->get('Content-Type'));
    }

    private function getPixel(): TrackingPixel
    {
        return new TrackingPixel(self::$inboxEmail->tracking_id);
    }

    private function getTracker(): EmailOpenTracker
    {
        return self::getService('test.email_open_tracker');
    }
}
