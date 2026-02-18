<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\InitiatedCharge;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class InitiatedChargeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return InitiatedCharge::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Initiated Charges')
            ->setDefaultSort(['id' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add('index', 'detail');
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IntegerField::new('id', 'ID');
        $tenant = AssociationField::new('tenant');
        $correlationId = TextField::new('correlation_id', 'Correlation ID');
        $currency = TextField::new('currency');
        $amount = NumberField::new('amount');
        $createdAt = DateTimeField::new('created_at');
        $applicationSource = TextField::new('application_source');
        $sourceId = IntegerField::new('source_id');
        $customer = IntegerField::new('customer_id');
        $merchantAccount = IntegerField::new('merchant_account_id');
        $gateway = TextField::new('gateway');
        $charge = CodeEditorField::new('charge')
            ->setNumOfRows(10)
            ->setLanguage('js');
        $parameters = CodeEditorField::new('parameters')
            ->setNumOfRows(10)
            ->setLanguage('js');
        $documents = TextField::new('documents', 'Documents');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $tenant, $currency, $amount, $gateway, $createdAt];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$charge];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$tenant, $currency, $amount, $customer, $documents, $applicationSource, $sourceId, $merchantAccount, $parameters, $createdAt, $correlationId, $gateway, $charge];
        }

        return [];
    }
}
