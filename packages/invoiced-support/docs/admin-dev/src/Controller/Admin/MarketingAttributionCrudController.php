<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\MarketingAttribution;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MarketingAttributionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MarketingAttribution::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Marketing Attributions')
            ->setSearchFields(['tenant_id', 'id', 'utm_campaign', 'utm_source', 'utm_content', 'utm_medium', 'utm_term', 'initial_referrer', 'initial_referring_domain'])
            ->setDefaultSort(['tenant_id' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->disable('new', 'delete', 'edit');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('utm_campaign')
            ->add('utm_source')
            ->add('utm_content')
            ->add('utm_medium');
    }

    public function configureFields(string $pageName): iterable
    {
        $tenantId = IntegerField::new('tenant_id');
        $utmCampaign = TextField::new('utm_campaign');
        $utmSource = TextField::new('utm_source');
        $utmContent = TextField::new('utm_content');
        $utmMedium = TextField::new('utm_medium');
        $utmTerm = TextField::new('utm_term');
        $initialReferrer = TextField::new('initial_referrer');
        $initialReferringDomain = TextField::new('initial_referring_domain');
        $tenant = AssociationField::new('tenant');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$tenantId, $utmCampaign, $utmSource, $utmContent, $utmMedium, $utmTerm, $initialReferrer, $initialReferringDomain];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$tenantId, $utmCampaign, $utmSource, $utmContent, $utmMedium, $utmTerm, $initialReferrer, $initialReferringDomain];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$tenantId, $utmCampaign, $utmSource, $utmContent, $utmMedium, $utmTerm, $initialReferrer, $initialReferringDomain, $tenant];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$tenantId, $utmCampaign, $utmSource, $utmContent, $utmMedium, $utmTerm, $initialReferrer, $initialReferringDomain, $tenant];
        }

        return [];
    }
}
