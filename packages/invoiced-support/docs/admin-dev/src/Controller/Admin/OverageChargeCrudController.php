<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\OverageCharge;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class OverageChargeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return OverageCharge::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Overage Charges')
            ->setSearchFields(['tenant_id'])
            ->setDefaultSort(['month' => 'DESC', 'total' => 'DESC'])
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
            ->add('tenant_id')
            ->add('month')
            ->add('quantity')
            ->add('price')
            ->add('total')
            ->add('billed')
            ->add('billing_system')
            ->add('dimension');
    }

    public function configureFields(string $pageName): iterable
    {
        $month = DateTimeField::new('month')->setFormat('MMMM yy');
        $dimension = TextField::new('dimension');
        $quantity = IntegerField::new('quantity');
        $price = NumberField::new('price');
        $total = NumberField::new('total');
        $billed = BooleanField::new('billed')->renderAsSwitch(false);
        $billingSystem = TextField::new('billing_system');
        $billingSystemId = TextField::new('billing_system_id');
        $failureMessage = TextField::new('failure_message');
        $tenant = AssociationField::new('tenant');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$month, $tenant, $dimension, $quantity, $total, $billed];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$month, $tenant, $dimension, $quantity, $price, $total, $billed, $billingSystem, $billingSystemId, $failureMessage];
        }

        return [];
    }
}
