<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\Feature;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class FeatureCrudController extends AbstractCrudController
{
    use ReturnToCompanyTrait;

    private const FEATURES = [
        '3DS Ignore' => '3ds_never',
        'ACH' => 'ach',
        'API' => 'api',
        'Accounting Sync' => 'accounting_sync',
        'Allow Accounting Record Edits' => 'accounting_record_edits',
        'Allow Invoiced Tokenization' => 'allow_invoiced_tokenization',
        'Audit Log' => 'audit_log',
        'AutoPay' => 'autopay',
        'Automations' => 'automations',
        'Bitcoin' => 'bitcoin',
        'Cash Application' => 'cash_application',
        'Cash Flow Forecasting' => 'forecasting',
        'CashMatch AI' => 'cash_match',
        'Consolidated Invoicing' => 'consolidated_invoicing',
        'Credit Card Payments' => 'card_payments',
        'Custom Domain' => 'custom_domain',
        'Custom Roles' => 'roles',
        'Custom Templates' => 'custom_templates',
        'Customer Portal' => 'billing_portal',
        'Direct Debit' => 'direct_debit',
        'Email Sending' => 'email_sending',
        'Email Whitelabeling' => 'email_whitelabel',
        'Enterprise' => 'enterprise',
        'Estimates V2' => 'estimates_v2',
        'Estimates' => 'estimates',
        'Flywire Full Sync' => 'flywire_full_sync',
        'Flywire Only' => 'flywire_only',
        'Flywire Priority Target Account' => 'flywire_mor_target',
        'Flywire Surcharging' => 'flywire_surcharging',
        'G/L Accounts' => 'gl_accounts',
        'Inboxes' => 'inboxes',
        'Intacct' => 'intacct',
        'Internationalization' => 'internationalization',
        'Invoice Chasing' => 'invoice_chasing',
        'Letter Mailing' => 'letters',
        'Live Chat' => 'live_chat',
        'Logging: Avalara' => 'log_avalara',
        'Logging: Business Central' => 'log_business_central',
        'Logging: Earth Class Mail' => 'log_earth_class_mail',
        'Logging: FreshBooks' => 'log_freshbooks',
        'Logging: Intacct' => 'log_intacct',
        'Logging: NetSuite' => 'log_netsuite',
        'Logging: Plaid' => 'log_plaid',
        'Logging: QuickBooks Desktop' => 'log_quickbooks_desktop',
        'Logging: QuickBooks Online' => 'log_quickbooks',
        'Logging: SMTP' => 'log_smtp',
        'Logging: Slack' => 'log_slack',
        'Logging: Xero' => 'log_xero',
        'Metered Billing' => 'metered_billing',
        'Multi-Currency' => 'multi_currency',
        'Needs Onboarding' => 'needs_onboarding',
        'NetSuite' => 'netsuite',
        'Network Invitations' => 'network_invitations',
        'Network' => 'network',
        'New Trials' => 'new_trials',
        'Not Activated' => 'not_activated',
        'Notifications v2: Enable for new users' => 'notifications_v2_default',
        'Notifications v2: Existing users can migrate' => 'notifications_v2_individual',
        'Payment Links' => 'payment_links',
        'Payment Plans' => 'payment_plans',
        'Phone Support' => 'phone_support',
        'Powered By Label' => 'powered_by',
        'Report Builder' => 'report_builder',
        'SAML' => 'saml',
        'SMS' => 'sms',
        'Salesforce' => 'salesforce',
        'Smart Chasing' => 'smart_chasing',
        'Subscription Billing' => 'subscription_billing',
        'Subscriptions Enabled' => 'subscriptions',
        'Unlimited Recipients' => 'unlimited_recipients',
        'Virtual Terminal' => 'virtual_terminal',
    ];

    public function __construct(
        private RequestStack $requestStack,
        private AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Feature::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Feature Flags')
            ->setSearchFields(['feature', 'tenant.name'])
            ->setDefaultSort(['feature' => 'ASC', 'tenant_id' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail');
    }

    public function configureFields(string $pageName): iterable
    {
        $feature = TextField::new('feature')
            ->setRequired(false);
        $featureChoice = ChoiceField::new('feature_choice', 'Select from list')
            ->setChoices(self::FEATURES);
        $enabled = BooleanField::new('enabled')->renderAsSwitch(false);
        $tenantId = IntegerField::new('tenant_id');
        $tenant = AssociationField::new('tenant');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$tenant, $feature, $enabled];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$feature, $enabled, $tenant];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$tenantId, $feature, $featureChoice, $enabled];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$enabled];
        }

        return [];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('tenant_id')
            ->add('feature')
            ->add('enabled');
    }

    public function createEntity(string $entityFqcn): Feature
    {
        $feature = new Feature();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();
        $tenantId = (int) $request->query->get('tenant_id');
        if ($tenantId > 0) {
            $feature->setTenantId($tenantId);
        }

        return $feature;
    }

    public function delete(AdminContext $context): Response
    {
        parent::delete($context);

        // redirect to tenant page
        /** @var Feature $feature */
        $feature = $context->getEntity()->getInstance();

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(CompanyCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($feature->getTenantId())
                ->generateUrl()
        );
    }
}
