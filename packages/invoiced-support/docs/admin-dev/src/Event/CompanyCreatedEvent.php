<?php

namespace App\Event;

use App\Entity\CustomerAdmin\NewAccount;
use Symfony\Contracts\EventDispatcher\Event;

class CompanyCreatedEvent extends Event
{
    private NewAccount $newAccount;
    private int $id;

    public function __construct(NewAccount $newAccount, int $id)
    {
        $this->newAccount = $newAccount;
        $this->id = $id;
    }

    public function getNewAccount(): NewAccount
    {
        return $this->newAccount;
    }

    public function getId(): int
    {
        return $this->id;
    }
}
