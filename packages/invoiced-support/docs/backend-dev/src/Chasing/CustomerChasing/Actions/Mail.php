<?php

namespace App\Chasing\CustomerChasing\Actions;

use App\Chasing\ValueObjects\ActionResult;
use App\Chasing\ValueObjects\ChasingEvent;
use App\Core\I18n\AddressFormatter;
use App\Sending\Mail\Exceptions\SendLetterException;
use App\Sending\Mail\Libs\LetterSender;
use App\Statements\Libs\StatementBuilder;

/**
 * Chasing action to send physically mail statements
 * to open accounts.
 */
class Mail extends AbstractAction
{
    public function __construct(private LetterSender $sender, private StatementBuilder $builder)
    {
    }

    public function execute(ChasingEvent $event): ActionResult
    {
        $customer = $event->getCustomer();

        $formatter = new AddressFormatter();
        $formatter->setFrom($customer->tenant());
        $from = $formatter->buildAddress();

        $formatter->setTo($customer);
        $to = $formatter->buildAddress();

        $statement = $this->builder->openItem($customer);

        try {
            $this->sender->send($customer, $statement, $from, $to);
        } catch (SendLetterException $e) {
            return new ActionResult(false, $e->getMessage());
        }

        return new ActionResult(true);
    }
}
