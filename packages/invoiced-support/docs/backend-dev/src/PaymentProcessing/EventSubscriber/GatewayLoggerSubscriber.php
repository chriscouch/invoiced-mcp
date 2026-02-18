<?php

namespace App\PaymentProcessing\EventSubscriber;

use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\PaymentProcessing\Libs\GatewayLogger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

class GatewayLoggerSubscriber implements EventSubscriberInterface, LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    public function __construct(
        private readonly GatewayLogger $gatewayLogger,
    ) {
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->gatewayLogger->flush($event->getRequest());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.terminate' => 'onKernelTerminate',
        ];
    }
}
