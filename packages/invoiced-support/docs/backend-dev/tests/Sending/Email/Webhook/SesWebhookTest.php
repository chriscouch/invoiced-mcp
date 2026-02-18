<?php

namespace App\Tests\Sending\Email\Webhook;

use App\Core\Mailer\EmailBlockList;
use App\Core\Mailer\EmailBlockReason;
use App\Sending\Email\Libs\SesWebhook;
use App\Sending\Email\Models\InboxEmail;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class SesWebhookTest extends AppTestCase
{
    private static SesWebhook $webhook;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasInbox();
        self::hasEmailThread();
        self::hasInboxEmail();

        self::$webhook = self::getService('test.ses_webhook');

        self::getService('test.database')->executeStatement('TRUNCATE BlockListEmailAddresses');
    }

    protected function setUp(): void
    {
        self::getService('test.tenant')->clear();
    }

    public function testHandleBouncePermanent(): void
    {
        self::getService('test.tenant')->set(self::$company);

        $json = '{
  "email": "sestest+block@example.com",
  "timestamp": "2022-02-25T22:57:45.683Z",
  "subject": "Mariana Hernandez has invited you to join MJH Intacct Sandbox on Invoiced",
  "expires": 1648480117,
  "messageId": "<lkasdjflksjdf@invoiced.com>",
  "from": "\"Invoiced (Sandbox)\" <no-reply@invoiced.com>",
  "bounceDetails": {
    "bounceSubType": "General",
    "feedbackId": "0111017f36938f8a-bfb61757-cdc3-461d-8850-a9781fb9629c-000000",
    "reportingMTA": "dns; e242-12.smtp-out.us-west-1.amazonses.com",
    "bounceType": "Permanent",
    "bouncedRecipients": [
      {
        "action": "failed",
        "emailAddress": "mariana@example.com",
        "diagnosticCode": "smtp; 550 4.4.7 Message expired: unable to deliver in 840 minutes.<451 4.0.0 Unknown>",
        "status": "4.4.7"
      }
    ],
    "timestamp": "2022-02-26T15:08:34.000Z"
  },
  "to": "MJ H <sestest+block@example.com>",
  "type": "Bounce"
}';
        $event = (array) json_decode($json, true);

        self::$webhook->handleBounce($event);

        $this->assertEquals(EmailBlockReason::PermanentBounce, $this->getBlockList()->isBlocked('sestest+block@example.com'));
    }

    public function testHandleBounceNoMessageId(): void
    {
        self::getService('test.tenant')->set(self::$company);

        $email = new InboxEmail();
        $email->thread = self::$thread;
        $email->incoming = false;
        $email->message_id = null;
        $email->saveOrFail();
        // message ID must be a blank string and not null
        $email::getDriver()->getConnection(null)->executeStatement('UPDATE InboxEmails SET InboxEmails.message_id="" WHERE id='.$email->id());

        $json = '{
  "email": "sestest+block2@example.com",
  "timestamp": "2022-02-25T22:57:45.683Z",
  "subject": "Mariana Hernandez has invited you to join MJH Intacct Sandbox on Invoiced",
  "expires": 1648480117,
  "messageId": "<29512e6f7c4336a2783d34a3ca3abe8c@invoiced.com>",
  "from": "\"Invoiced (Sandbox)\" <no-reply@invoiced.com>",
  "bounceDetails": {
    "bounceSubType": "General",
    "feedbackId": "0111017f36938f8a-bfb61757-cdc3-461d-8850-a9781fb9629c-000000",
    "reportingMTA": "dns; e242-12.smtp-out.us-west-1.amazonses.com",
    "bounceType": "Transient",
    "bouncedRecipients": [
      {
        "action": "failed",
        "emailAddress": "mariana@example.com",
        "diagnosticCode": "smtp; 550 4.4.7 Message expired: unable to deliver in 840 minutes.<451 4.0.0 Unknown>",
        "status": "4.4.7"
      }
    ],
    "timestamp": "2022-02-26T15:08:34.000Z"
  },
  "to": "MJ H <sestest+block2@example.com>",
  "type": "Bounce"
}';
        $event = (array) json_decode($json, true);

        self::$webhook->handleBounce($event);

        self::getService('test.tenant')->set(self::$company);

        // should NOT create a new email in the thread
        $this->assertEquals(0, InboxEmail::where('reply_to_email_id', $email)->count());

        // Transient bounces should not block
        $this->assertNull($this->getBlockList()->isBlocked('sestest+block2@example.com'));
    }

    public function testHandleBounceInbox(): void
    {
        self::getService('test.tenant')->set(self::$company);

        $json = '{
  "email": "sestest+block3@example.com",
  "timestamp": "2022-02-25T22:57:45.683Z",
  "subject": "Mariana Hernandez has invited you to join MJH Intacct Sandbox on Invoiced",
  "expires": 1648480117,
  "messageId": "<29512e6f7c4336a2783d34a3ca3abe8c@invoiced.com>",
  "from": "\"Invoiced (Sandbox)\" <no-reply@invoiced.com>",
  "bounceDetails": {
    "bounceSubType": "General",
    "feedbackId": "0111017f36938f8a-bfb61757-cdc3-461d-8850-a9781fb9629c-000000",
    "reportingMTA": "dns; e242-12.smtp-out.us-west-1.amazonses.com",
    "bounceType": "Transient",
    "bouncedRecipients": [
      {
        "action": "failed",
        "emailAddress": "mariana@example.com",
        "diagnosticCode": "smtp; 550 4.4.7 Message expired: unable to deliver in 840 minutes.<451 4.0.0 Unknown>",
        "status": "4.4.7"
      }
    ],
    "timestamp": "2022-02-26T15:08:34.000Z"
  },
  "awsMessageId": "'.self::$inboxEmail->message_id.'",
  "to": "MJ H <sestest+block3@example.com>",
  "type": "Bounce"
}';
        $event = (array) json_decode($json, true);

        self::$webhook->handleBounce($event);

        self::getService('test.tenant')->set(self::$company);

        // should create a new email in the thread
        $email = InboxEmail::where('thread_id', self::$thread)
            ->sort('id DESC')
            ->one();
        $this->assertNotEquals(self::$inboxEmail->id(), $email->id());
        $this->assertTrue($email->incoming);
        $this->assertEquals('Delivery Status Notification (Failure)', $email->subject);
        $this->assertEquals(self::$inboxEmail->thread_id, $email->thread_id);
        $this->assertEquals(self::$inboxEmail->id(), $email->reply_to_email_id);
        $this->assertTrue(self::$inboxEmail->refresh()->bounce);

        // Transient bounces should not block
        $this->assertNull($this->getBlockList()->isBlocked('sestest+block3@example.com'));
    }

    public function testHandleComplaintInbox(): void
    {
        self::getService('test.tenant')->set(self::$company);

        $json = '{
  "email": "sestest+complaint@example.com",
  "timestamp": "2022-02-25T22:57:45.683Z",
  "subject": "Mariana Hernandez has invited you to join MJH Intacct Sandbox on Invoiced",
  "expires": 1648480117,
  "messageId": "<29512e6f7c4336a2783d34a3ca3abe8c@invoiced.com>",
  "from": "\"Invoiced (Sandbox)\" <no-reply@invoiced.com>",
  "complaintDetails": {
    "timestamp": "2024-04-08T13:02:45.000Z",
    "arrivalDate": "2024-04-08T12:06:30.000Z",
    "complainedRecipients": [
      {
        "emailAddress": "mariana@example.com"
      }
    ],
    "complaintFeedbackType": "abuse",
    "complaintSubType": null,
    "feedbackId": "010f018ebdcdd086-4a1ace42-076b-47e6-9e53-4285957acd7b-000000",
    "userAgent": "ReturnPathFBL/2.0"
  },
  "awsMessageId": "'.self::$inboxEmail->message_id.'",
  "to": "MJ H <sestest+complaint@example.com>",
  "type": "Complaint"
}';
        $event = (array) json_decode($json, true);

        self::$webhook->handleComplaint($event);

        $this->assertTrue(self::$inboxEmail->refresh()->complaint);

        $this->assertNull($this->getBlockList()->isBlocked('sestest+complaint@example.com'));

        // 2 more complaints should result in a block
        self::$webhook->handleComplaint($event);
        self::$webhook->handleComplaint($event);
        $this->assertEquals(EmailBlockReason::Complaint, $this->getBlockList()->isBlocked('sestest+complaint@example.com'));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testHandle(): void
    {
        $json = '{
  "email": "regan.chan@example.com",
  "timestamp": "2022-02-26T06:20:10.363Z",
  "subject": "AutoPay attempt failed",
  "expires": 1648448413,
  "messageId": "<25eb1c6be9ee9c957907b8416d9e27e3@invoiced.com>",
  "from": "\"Invoiced (Sandbox)\" <no-reply@invoiced.com>",
  "awsMessageId": "<0111017f34afcafb-de4d309f-43d8-4acf-aa44-b5a577239592-000000@us-east-2.amazonses.com>",
  "to": "Regan <regan.chan@example.com>",
  "type": "Delivery"
}';
        $event = (array) json_decode($json, true);

        $request = Request::create('/');
        $request->request->add($event);

        self::$webhook->handle($request);
    }

    private function getBlockList(): EmailBlockList
    {
        return new EmailBlockList(self::getService('test.database'));
    }
}
