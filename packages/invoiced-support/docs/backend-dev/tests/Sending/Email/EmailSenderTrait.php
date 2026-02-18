<?php

namespace App\Tests\Sending\Email;

use App\Core\Statsd\StatsdClient;
use App\Sending\Email\Libs\AdapterFactory;
use App\Sending\Email\Libs\EmailSender;

trait EmailSenderTrait
{
    protected function getSender(?AdapterFactory $adapterFactory = null): EmailSender
    {
        $adapterFactory = $adapterFactory ?? self::getService('test.email_adapter_factory');
        $sender = new EmailSender($adapterFactory, self::getService('test.lock_factory'), self::getService('test.outgoing_email_writer_factory'), 'test');
        $sender->setStatsd(new StatsdClient());

        return $sender;
    }
}
