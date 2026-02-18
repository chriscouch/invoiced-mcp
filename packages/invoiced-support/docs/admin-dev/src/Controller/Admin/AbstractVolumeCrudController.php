<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

abstract class AbstractVolumeCrudController extends AbstractCrudController
{
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(['tenant_id'])
            ->setDefaultSort(['month' => 'DESC', 'count' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->disable('new', 'delete');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('tenant_id')
            ->add('month')
            ->add('do_not_bill');
    }

    public function configureFields(string $pageName): iterable
    {
        $tenantId = IntegerField::new('tenant_id');
        $month = DateTimeField::new('month')->setFormat('MMMM yy');
        $count = IntegerField::new('count');
        $doNotBill = BooleanField::new('do_not_bill')->renderAsSwitch(false);
        $doNotBill2 = BooleanField::new('do_not_bill');
        $tenant = AssociationField::new('tenant');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$month, $tenant, $count, $doNotBill];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$month, $count, $tenant, $doNotBill];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$tenantId, $month, $count, $doNotBill2, $tenant];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$doNotBill2];
        }

        return [];
    }
}
