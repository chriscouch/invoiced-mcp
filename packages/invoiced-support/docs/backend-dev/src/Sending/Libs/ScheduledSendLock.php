<?php

namespace App\Sending\Libs;

use App\Companies\Models\Company;
use App\Core\Utils\ModelLock;
use App\Sending\Models\ScheduledSend;
use Symfony\Component\Lock\LockFactory;

class ScheduledSendLock extends ModelLock
{
    public function __construct(LockFactory $lockFactory, Company $tenant, ScheduledSend $model)
    {
        $this->factory = $lockFactory;
        $this->name = 'schedule_send.'.$tenant->id().'_'.$model->id;
    }
}
