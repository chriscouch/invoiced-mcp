<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\Member;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MemberCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Member::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Company Members')
            ->setSearchFields(['id', 'user_id', 'tenant_id', 'role', 'expires', 'last_accessed', 'restriction_mode', 'restrictions'])
            ->setDefaultSort(['id' => 'ASC'])
            ->overrideTemplate('crud/detail', 'customizations/show/member_show.html.twig')
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->disable('delete', 'new');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('tenant_id');
    }

    public function configureFields(string $pageName): iterable
    {
        $createdAt = DateTimeField::new('created_at', 'Date Created');
        $updatedAt = DateTimeField::new('updated_at', 'Last Updated');
        $role = TextField::new('role');
        $expires = DateTimeField::new('expires');
        $lastAccessed = DateTimeField::new('last_accessed', 'Last Accessed');
        $restrictionMode = TextField::new('restriction_mode', 'Restriction Mode');
        $restrictions = TextField::new('restrictions');
        $notifications = BooleanField::new('notifications', 'Notifications v2');
        $subscribeAll = BooleanField::new('subscribe_all', 'Subscribe All');
        $user = AssociationField::new('user');
        $tenant = AssociationField::new('tenant');
        $id = IntegerField::new('id', 'ID');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $tenant, $user, $role, $lastAccessed];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$user, $tenant, $createdAt, $updatedAt, $expires, $lastAccessed, FormField::addPanel('Permissions'), $role, $restrictionMode, $restrictions, FormField::addPanel('Notifications'), $notifications, $subscribeAll];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [FormField::addPanel('Notifications'), $notifications];
        }

        return [];
    }
}
