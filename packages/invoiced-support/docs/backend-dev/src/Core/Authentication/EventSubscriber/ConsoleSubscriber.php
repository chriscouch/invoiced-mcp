<?php

namespace App\Core\Authentication\EventSubscriber;

use App\Core\Authentication\Libs\UserContext;
use App\Core\Authentication\Models\User;
use App\Core\Orm\ACLModelRequester;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConsoleSubscriber implements EventSubscriberInterface
{
    public function __construct(private UserContext $userContext)
    {
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $user = new User(['id' => User::INVOICED_USER]);
        $this->userContext->set($user);

        // use the Invoiced user as the requester for model permissions
        ACLModelRequester::set($user);
    }

    public static function getSubscribedEvents(): array
    {
        return [
           'console.command' => 'onConsoleCommand',
        ];
    }
}
