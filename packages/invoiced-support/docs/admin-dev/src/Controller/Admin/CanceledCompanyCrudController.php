<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\CanceledCompany;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CanceledCompanyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CanceledCompany::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Canceled Companies')
            ->setSearchFields(['id', 'name', 'username', 'email', 'creator.first_name', 'creator.last_name', 'creator.id'])
            ->setDefaultSort(['canceled_at' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->remove('detail', 'index')
            ->disable('delete', 'new', 'edit');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('id')
            ->add('name')
            ->add('email')
            ->add('username')
            ->add('city')
            ->add('state')
            ->add('country');
    }

    public function configureFields(string $pageName): iterable
    {
        $name = TextField::new('name');
        $email = TextField::new('email');
        $username = TextField::new('username');
        $customDomain = TextField::new('custom_domain');
        $type = TextField::new('type');
        $address1 = TextField::new('address1', 'Address Line 1');
        $address2 = TextField::new('address2', 'Address Line 2');
        $city = TextField::new('city');
        $state = TextField::new('state');
        $postalCode = TextField::new('postal_code', 'Postal Code');
        $country = CountryField::new('country');
        $taxId = TextField::new('tax_id');
        $addressExtra = TextareaField::new('address_extra');
        $canceledAt = DateTimeField::new('canceled_at', 'Date Canceled');
        $canceledReason = TextField::new('canceled_reason', 'Reason For Canceling');
        $industry = TextField::new('industry');
        $createdAt = DateTimeField::new('created_at');
        $creator = AssociationField::new('creator');
        $billingProfile = AssociationField::new('billingProfile');
        $id = IntegerField::new('id', 'ID');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $name, $email, $createdAt, $canceledAt, $canceledReason];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [
                $billingProfile,
                $name,
                $username,
                $creator,
                $createdAt,
                $canceledAt,
                $canceledReason,
                $address1,
                $address2,
                $city,
                $state,
                $postalCode,
                $country,
                $type,
                $taxId,
                $addressExtra,
                $industry,
                $customDomain,
            ];
        }

        return [];
    }
}
