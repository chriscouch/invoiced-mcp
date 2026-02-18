<?php

namespace App\Controller\Admin;

use App\Entity\CustomerAdmin\AuditEntry;
use App\Entity\CustomerAdmin\User;
use Carbon\CarbonImmutable;

trait CrudControllerTrait
{
    private function addAuditEntry(string $action, string $context): void
    {
        $em = $this->getDoctrine()->getManager('CustomerAdmin_ORM');
        $entry = new AuditEntry();
        $entry->setTimestamp(CarbonImmutable::now());
        /** @var User $user */
        $user = $this->getUser();
        $entry->setUser($user->getUsername());
        $entry->setAction($action);
        $entry->setContext($context);
        $em->persist($entry);
        $em->flush();
    }
}
