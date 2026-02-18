<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\AccountSecurityEvent;
use App\Service\IpInfoLookup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class AccountSecurityEventCrudController extends AbstractCrudController
{
    public function __construct(private IpInfoLookup $ipInfoLookup)
    {
    }

    public static function getEntityFqcn(): string
    {
        return AccountSecurityEvent::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Account Security Events')
            ->setSearchFields(['user_id'])
            ->setDefaultSort(['id' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->disable('edit', 'new', 'delete');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('user_id', 'User ID'))
            ->add(TextFilter::new('user_agent', 'User Agent'))
            ->add(TextFilter::new('auth_strategy', 'Auth Strategy'))
            ->add(TextFilter::new('ip', 'IP Address'));
    }

    public function configureFields(string $pageName): iterable
    {
        $type = TextField::new('type');
        $ip = TextField::new('ip', 'IP Address')
            ->formatValue([$this->ipInfoLookup, 'makeIpInfoLink']);
        $userAgent = TextField::new('user_agent', 'User Agent');
        $description = TextField::new('description');
        $authStrategy = TextField::new('auth_strategy', 'Auth Strategy');
        $createdAt = DateTimeField::new('created_at', 'Timestamp');
        $user = AssociationField::new('user');
        $id = IntegerField::new('id', 'ID');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $user, $type, $ip, $authStrategy, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$user, $createdAt, $type, $ip, $authStrategy, $userAgent, $description];
        }

        return [];
    }
}
