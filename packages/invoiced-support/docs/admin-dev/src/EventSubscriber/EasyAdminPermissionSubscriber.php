<?php

namespace App\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Security\AuthorizationChecker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;

class EasyAdminPermissionSubscriber implements EventSubscriberInterface
{
    private const PERMISSION_MAP = [
        'index' => 'list',
        'detail' => 'show',
        'new' => 'new',
        'edit' => 'edit',
        'delete' => 'delete',
    ];

    private AuthorizationChecker $authorizationChecker;

    public function __construct(AuthorizationChecker $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    public function onBeforeCrudActionEvent(BeforeCrudActionEvent $event): void
    {
        $context = $event->getAdminContext();
        if (!$context instanceof AdminContext) {
            return;
        }

        $crud = $context->getCrud();
        $action = null === $crud ? null : $crud->getCurrentAction();
        $entityClass = $context->getEntity()->getFqcn();
        $entity = $context->getEntity()->getInstance() ?: new $entityClass();

        if (isset(self::PERMISSION_MAP[$action])) {
            if (!$this->authorizationChecker->isGranted(self::PERMISSION_MAP[$action], $entity)) {
                throw new ForbiddenActionException($context);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeCrudActionEvent::class => 'onBeforeCrudActionEvent',
        ];
    }
}
