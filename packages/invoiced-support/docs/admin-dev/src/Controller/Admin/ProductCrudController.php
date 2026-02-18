<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\Product;
use App\Form\ProductFeatureType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Products')
            ->setSearchFields(['id', 'name'])
            ->setDefaultSort(['name' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->setPermission('new', 'new')
            ->setPermission('edit', 'edit')
            ->setPermission('delete', 'delete');
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IntegerField::new('id', 'ID');
        $name = TextField::new('name', 'Name');
        $features = CollectionField::new('features', 'Features')
            ->setEntryType(ProductFeatureType::class);

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $name];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $features];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$name, $features];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$name, $features];
        }

        return [];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', 'Name'));
    }
}
