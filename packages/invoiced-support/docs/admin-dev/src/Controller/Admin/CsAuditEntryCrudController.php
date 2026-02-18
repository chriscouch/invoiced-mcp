<?php

namespace App\Controller\Admin;

use App\Entity\CustomerAdmin\AuditEntry;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CsAuditEntryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AuditEntry::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Audit Log')
            ->setSearchFields(['id', 'user', 'action', 'context'])
            ->setDefaultSort(['timestamp' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->disable('new', 'edit', 'delete');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('timestamp')
            ->add('user')
            ->add('action')
            ->add('context');
    }

    public function configureFields(string $pageName): iterable
    {
        $timestamp = DateTimeField::new('timestamp');
        $user = TextField::new('user');
        $action = TextField::new('action');
        $context = TextField::new('context');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$timestamp, $user, $action, $context];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$timestamp, $user, $action, $context];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$timestamp, $user, $action, $context];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$timestamp, $user, $action, $context];
        }

        return [];
    }
}
