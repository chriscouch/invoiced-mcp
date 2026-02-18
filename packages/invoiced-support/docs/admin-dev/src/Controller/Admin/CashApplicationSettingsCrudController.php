<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\CashApplicationSettings;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CashApplicationSettingsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CashApplicationSettings::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Cash Application Settings')
            ->setEntityLabelInPlural('Cash Application Settings')
            ->setSearchFields(['tenant_id'])
            ->setDefaultSort(['tenant_id' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->disable('new', 'index', 'edit', 'delete');
    }

    public function configureFields(string $pageName): iterable
    {
        $shortPayUnits = TextField::new('short_pay_units');
        $shortPayAmount = IntegerField::new('short_pay_amount');
        $tenant = AssociationField::new('tenant');

        if (Crud::PAGE_DETAIL === $pageName) {
            return [
                $tenant,
                $shortPayUnits,
                $shortPayAmount,
            ];
        }

        return [];
    }
}
