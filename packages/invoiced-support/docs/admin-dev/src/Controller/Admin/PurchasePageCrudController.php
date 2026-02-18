<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\PurchasePageContext;
use App\Service\IpInfoLookup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NullFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class PurchasePageCrudController extends AbstractCrudController
{
    use CrudControllerTrait;

    const REASONS = [
        'New Company' => 1,
        'Activate' => 2,
        'Upgrade' => 3,
        'Reactivate' => 4,
    ];

    const PAYMENT_TERMS = [
        'AutoPay' => 1,
        'Net 30' => 2,
        'None' => 3,
    ];

    public function __construct(
        private string $apiLogEnvironment,
        private IpInfoLookup $ipInfoLookup,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PurchasePageContext::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Purchase Page')
            ->setEntityLabelInPlural('Purchase Pages')
            ->setSearchFields(['id', 'identifier', 'billingProfile.name', 'sales_rep', 'completed_by_name'])
            ->setDefaultSort(['id' => 'DESC'])
            ->overrideTemplate('crud/index', 'customizations/list/purchase_page_list.html.twig')
            ->overrideTemplate('crud/detail', 'customizations/show/purchase_page_show.html.twig')
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->setPermission('index', 'list')
            ->setPermission('detail', 'show')
            ->setPermission('edit', 'edit')
            ->setPermission('delete', 'delete')
            ->disable('new');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('reason', 'Type')->setChoices(self::REASONS))
            ->add(ChoiceFilter::new('payment_terms', 'Payment Terms')->setChoices(self::PAYMENT_TERMS))
            ->add(TextFilter::new('sales_rep', 'Sales Rep'))
            ->add(TextFilter::new('country', 'Country'))
            ->add(DateTimeFilter::new('expiration_date', 'Expiration Date'))
            ->add(DateTimeFilter::new('completed_at', 'Completed At'))
            ->add(NullFilter::new('completed_by_name', 'Completed')->setChoiceLabels('Not Completed', 'Completed'));
    }

    public function configureFields(string $pageName): iterable
    {
        $rootDomain = match ($this->apiLogEnvironment) {
            'sandbox' => 'https://sandbox.invoiced.com',
            'staging' => 'https://staging.invoiced.com',
            'dev' => 'http://invoiced.localhost:1234',
            default => 'https://invoiced.com',
        };

        $id = IntegerField::new('id', 'ID');
        $identifier = TextField::new('identifier', 'URL')
            ->formatValue(function ($value) use ($rootDomain) {
                return '<a href="'.$rootDomain.'/purchase/'.$value.'" target="_blank">View Page</a>';
            });
        $billingProfile = AssociationField::new('billingProfile', 'Billing Profile');
        $company = AssociationField::new('tenant', 'Company');
        $expirationDate = DateField::new('expiration_date', 'Expiration Date');
        $reason = ChoiceField::new('reason', 'Type')->setChoices(self::REASONS);
        $salesRep = TextField::new('sales_rep', 'Sales Rep');
        $country = CountryField::new('country', 'Country');
        $localizedPricing = BooleanField::new('localized_pricing', 'Localized Pricing')
            ->renderAsSwitch(false);
        $paymentTerms = ChoiceField::new('payment_terms', 'Payment Terms')->setChoices(self::PAYMENT_TERMS);
        $changesetFormatted = TextField::new('changesetFormatted', 'Changeset');
        $changeset = CodeEditorField::new('changeset')
            ->setNumOfRows(5)
            ->setLanguage('js');
        $activationFee = MoneyField::new('activation_fee', 'Activation Fee')->setCurrency('USD')->setStoredAsCents(false);
        $note = TextareaField::new('note', 'Note');
        $completedByName = TextField::new('completed_by_name', 'Completed By Name');
        $completedByIp = TextField::new('completed_by_ip', 'Completed By IP')
            ->formatValue([$this->ipInfoLookup, 'makeIpInfoLink']);
        $completedAt = DateTimeField::new('completed_at', 'Completed At');
        $lastViewed = DateTimeField::new('last_viewed', 'Last Viewed');
        $createdAt = DateTimeField::new('created_at', 'Date Created');
        $updatedAt = DateTimeField::new('updated_at', 'Last Updated');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $billingProfile, $reason, $paymentTerms, $salesRep, $expirationDate, $identifier, $completedAt];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$expirationDate, $paymentTerms, $localizedPricing, $activationFee, $note, $changeset];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [
                $identifier,
                $billingProfile,
                $company,
                $reason,
                $salesRep,
                $country,
                $localizedPricing,
                $paymentTerms,
                $changesetFormatted,
                $activationFee,
                $note,
                $expirationDate,
                $createdAt,
                $updatedAt,
                FormField::addPanel('Completion Detail'),
                $lastViewed,
                $completedAt,
                $completedByName,
                $completedByIp,
            ];
        }

        return [];
    }
}
