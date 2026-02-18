<?php

namespace App\Event;

use App\Entity\CustomerAdmin\Order;
use Symfony\Contracts\EventDispatcher\Event;

class PostOrderEvent extends Event
{
    private Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }
}
