<?php

namespace App\Chasing\CustomerChasing;

use App\Chasing\CustomerChasing\Actions\Email;
use App\Chasing\CustomerChasing\Actions\Escalate;
use App\Chasing\CustomerChasing\Actions\Mail;
use App\Chasing\CustomerChasing\Actions\PhoneCall;
use App\Chasing\CustomerChasing\Actions\SMS;
use App\Chasing\Interfaces\ActionInterface;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\ValueObjects\ActionResult;
use App\Chasing\ValueObjects\ChasingEvent;
use Psr\Container\ContainerInterface;

/**
 * Wrapper around a Symfony service locator for chasing action types.
 */
class ActionCollection
{
    private static array $classes = [
        ChasingCadenceStep::ACTION_MAIL => Mail::class,
        ChasingCadenceStep::ACTION_EMAIL => Email::class,
        ChasingCadenceStep::ACTION_ESCALATE => Escalate::class,
        ChasingCadenceStep::ACTION_PHONE => PhoneCall::class,
        ChasingCadenceStep::ACTION_SMS => SMS::class,
    ];

    public function __construct(private ContainerInterface $locator)
    {
    }

    /**
     * Retrieves a chasing action object based on its string type name.
     */
    public function getForType(string $type): ActionInterface
    {
        return $this->locator->get(self::$classes[$type]);
    }

    /**
     * Retrieves a chasing action object based on its string type name.
     */
    public function get(ChasingEvent $chasingEvent): ActionInterface
    {
        return $this->locator->get(self::$classes[$chasingEvent->getStep()->action]);
    }

    /**
     * Executes a chasing event with the correct action type.
     */
    public function execute(ChasingEvent $chasingEvent): ActionResult
    {
        return $this->getForType($chasingEvent->getStep()->action)
            ->execute($chasingEvent);
    }
}
