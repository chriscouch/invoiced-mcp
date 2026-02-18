<?php

namespace App\Controller\Admin;

use App\Entity\CustomerAdmin\User;
use App\Entity\Invoiced\AccountingSyncProfile;
use App\Entity\Invoiced\AdyenAccount;
use App\Entity\Invoiced\Company;
use App\Entity\Invoiced\Member;
use App\Entity\Invoiced\User as InvUser;
use App\Service\CsAdminApiClient;
use Carbon\CarbonImmutable;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AvatarField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class CompanyCrudController extends AbstractCrudController
{
    use CrudControllerTrait;

    private const CURRENCIES = [
        'aed' => 'aed',
        'afn' => 'afn',
        'all' => 'all',
        'amd' => 'amd',
        'ang' => 'ang',
        'aoa' => 'aoa',
        'ars' => 'ars',
        'aud' => 'aud',
        'awg' => 'awg',
        'azn' => 'azn',
        'bam' => 'bam',
        'bbd' => 'bbd',
        'bdt' => 'bdt',
        'bgn' => 'bgn',
        'bhd' => 'bhd',
        'bif' => 'bif',
        'bmd' => 'bmd',
        'bnd' => 'bnd',
        'bob' => 'bob',
        'brl' => 'brl',
        'bsd' => 'bsd',
        'btc' => 'btc',
        'btn' => 'btn',
        'bwp' => 'bwp',
        'byr' => 'byr',
        'bzd' => 'bzd',
        'cad' => 'cad',
        'cdf' => 'cdf',
        'chf' => 'chf',
        'clp' => 'clp',
        'cny' => 'cny',
        'cop' => 'cop',
        'crc' => 'crc',
        'cuc' => 'cuc',
        'cup' => 'cup',
        'cve' => 'cve',
        'czk' => 'czk',
        'djf' => 'djf',
        'dkk' => 'dkk',
        'dop' => 'dop',
        'dzd' => 'dzd',
        'egp' => 'egp',
        'ern' => 'ern',
        'etb' => 'etb',
        'eur' => 'eur',
        'fjd' => 'fjd',
        'fkp' => 'fkp',
        'gbp' => 'gbp',
        'gel' => 'gel',
        'ggp' => 'ggp',
        'ghs' => 'ghs',
        'gip' => 'gip',
        'gmd' => 'gmd',
        'gnf' => 'gnf',
        'gtq' => 'gtq',
        'gyd' => 'gyd',
        'hkd' => 'hkd',
        'hnl' => 'hnl',
        'hrk' => 'hrk',
        'htg' => 'htg',
        'huf' => 'huf',
        'idr' => 'idr',
        'ils' => 'ils',
        'imp' => 'imp',
        'inr' => 'inr',
        'iqd' => 'iqd',
        'irr' => 'irr',
        'isk' => 'isk',
        'jep' => 'jep',
        'jmd' => 'jmd',
        'jod' => 'jod',
        'jpy' => 'jpy',
        'kes' => 'kes',
        'kgs' => 'kgs',
        'khr' => 'khr',
        'kmf' => 'kmf',
        'kpw' => 'kpw',
        'krw' => 'krw',
        'kwd' => 'kwd',
        'kyd' => 'kyd',
        'kzt' => 'kzt',
        'lak' => 'lak',
        'lbp' => 'lbp',
        'lkr' => 'lkr',
        'lrd' => 'lrd',
        'lsl' => 'lsl',
        'ltl' => 'ltl',
        'lvl' => 'lvl',
        'lyd' => 'lyd',
        'mad' => 'mad',
        'mdl' => 'mdl',
        'mga' => 'mga',
        'mkd' => 'mkd',
        'mmk' => 'mmk',
        'mnt' => 'mnt',
        'mop' => 'mop',
        'mro' => 'mro',
        'mur' => 'mur',
        'mvr' => 'mvr',
        'mwk' => 'mwk',
        'mxn' => 'mxn',
        'myr' => 'myr',
        'mzn' => 'mzn',
        'nad' => 'nad',
        'ngn' => 'ngn',
        'nio' => 'nio',
        'nok' => 'nok',
        'npr' => 'npr',
        'nzd' => 'nzd',
        'omr' => 'omr',
        'pab' => 'pab',
        'pen' => 'pen',
        'pgk' => 'pgk',
        'php' => 'php',
        'pkr' => 'pkr',
        'pln' => 'pln',
        'pyg' => 'pyg',
        'qar' => 'qar',
        'ron' => 'ron',
        'rsd' => 'rsd',
        'rub' => 'rub',
        'rwf' => 'rwf',
        'sar' => 'sar',
        'sbd' => 'sbd',
        'scr' => 'scr',
        'sdg' => 'sdg',
        'sek' => 'sek',
        'sgd' => 'sgd',
        'shp' => 'shp',
        'sll' => 'sll',
        'sos' => 'sos',
        'spl' => 'spl',
        'srd' => 'srd',
        'std' => 'std',
        'svc' => 'svc',
        'syp' => 'syp',
        'szl' => 'szl',
        'thb' => 'thb',
        'tjs' => 'tjs',
        'tmt' => 'tmt',
        'tnd' => 'tnd',
        'top' => 'top',
        'try' => 'try',
        'ttd' => 'ttd',
        'tvd' => 'tvd',
        'twd' => 'twd',
        'tzs' => 'tzs',
        'uah' => 'uah',
        'ugx' => 'ugx',
        'usd' => 'usd',
        'uyu' => 'uyu',
        'uzs' => 'uzs',
        'vef' => 'vef',
        'vnd' => 'vnd',
        'vuv' => 'vuv',
        'wst' => 'wst',
        'xaf' => 'xaf',
        'xcd' => 'xcd',
        'xcg' => 'xcg',
        'xdr' => 'xdr',
        'xof' => 'xof',
        'xpf' => 'xpf',
        'yer' => 'yer',
        'zar' => 'zar',
        'zmw' => 'zmw',
        'zwd' => 'zwd',
    ];

    private const COMPANY_TYPE = [
        'Company' => 'company',
        'Government' => 'government',
        'Non-Profit' => 'non_profit',
        'Person' => 'person',
    ];

    private const INDUSTRIES = [
        'Academia' => 'Academia',
        'Accounting' => 'Accounting',
        'Animal care' => 'Animal care',
        'Agriculture' => 'Agriculture',
        'Apparel' => 'Apparel',
        'Banking' => 'Banking',
        'Beauty and Cosmetics' => 'Beauty and Cosmetics',
        'Biotechnology' => 'Biotechnology',
        'Business Services' => 'Business Services',
        'Chemicals' => 'Chemicals',
        'Communications' => 'Communications',
        'Construction' => 'Construction',
        'Consulting' => 'Consulting',
        'Distribution' => 'Distribution',
        'Education' => 'Education',
        'Electronics' => 'Electronics',
        'Energy' => 'Energy',
        'Engineering' => 'Engineering',
        'Entertainment' => 'Entertainment',
        'Environmental' => 'Environmental',
        'Finance' => 'Finance',
        'Food and Beverage' => 'Food and Beverage',
        'Government and Public services' => 'Government and Public services',
        'Grant and Fundraising' => 'Grant and Fundraising',
        'Healthcare' => 'Healthcare',
        'Hospitality' => 'Hospitality',
        'HR and Recruitment' => 'HR and Recruitment',
        'Insurance' => 'Insurance',
        'Legal Services' => 'Legal Services',
        'Machinery' => 'Machinery',
        'Manufacturing' => 'Manufacturing',
        'Marketing and Advertising' => 'Marketing and Advertising',
        'Media' => 'Media',
        'Medical Equipment and Supplies' => 'Medical Equipment and Supplies',
        'Not for profit' => 'Not for profit',
        'Oil and Gas' => 'Oil and Gas',
        'Pharmaceutical' => 'Pharmaceutical',
        'Real Estate' => 'Real Estate',
        'Recreation' => 'Recreation',
        'Recruitment' => 'Recruitment',
        'Research (non-academic)' => 'Research (non-academic)',
        'Retail' => 'Retail',
        'Security Services' => 'Security Services',
        'Shipping' => 'Shipping',
        'Software and IT' => 'Software and IT',
        'Technology Hardware' => 'Technology Hardware',
        'Telecommunications' => 'Telecommunications',
        'Transportation' => 'Transportation',
        'Travel' => 'Travel',
        'Utilities' => 'Utilities',
        'Other' => 'Other',
    ];

    public function __construct(
        private CsAdminApiClient $csAdminApiClient,
        private AdminUrlGenerator $adminUrlGenerator,
        private ManagerRegistry $managerRegistry,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Company::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Companies')
            ->setSearchFields(['id', 'name', 'username', 'email'])
            ->overrideTemplate('crud/index', 'customizations/list/company_list.html.twig')
            ->overrideTemplate('crud/detail', 'customizations/show/company_show.html.twig')
            ->setDefaultSort(['id' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->remove('detail', 'index')
            ->disable('delete', 'new');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('id')
            ->add('name')
            ->add('email')
            ->add('username')
            ->add('identifier')
            ->add('city')
            ->add('state')
            ->add('country')
            ->add('type')
            ->add('industry')
            ->add('canceled')
            ->add('fraud')
            ->add('trial_ends');
    }

    public function configureFields(string $pageName): iterable
    {
        $name = TextField::new('name');
        $dba = TextField::new('nickname', 'DBA');
        $fraud = Field::new('fraud', 'Fraudulent Company');
        $email = TextField::new('email');
        $type = ChoiceField::new('type', 'Entity Type')->setChoices(self::COMPANY_TYPE);
        $address1 = TextField::new('address1', 'Address Line 1');
        $address2 = TextField::new('address2', 'Address Line 2');
        $city = TextField::new('city');
        $state = TextField::new('state');
        $postalCode = TextField::new('postal_code', 'Postal Code');
        $currency = ChoiceField::new('currency')->setChoices(self::CURRENCIES);
        $logo = AvatarField::new('logo', false);
        $trialEnds = DateTimeField::new('trial_ends', 'Trial End Date');
        $canceled = BooleanField::new('canceled')
            ->renderAsSwitch(false);
        $canceledAt = DateTimeField::new('canceled_at', 'Canceled At');
        $billingProfile = AssociationField::new('billingProfile', 'Billing Profile')
            ->autocomplete();
        $username = TextField::new('username');
        $customDomain = TextField::new('custom_domain');
        $country = CountryField::new('country');
        $language = TextField::new('language');
        $taxId = TextField::new('tax_id', 'Tax Id');
        $testMode = Field::new('test_mode', 'Test Mode');
        $industry = ChoiceField::new('industry')->setChoices(self::INDUSTRIES);
        $panel1 = FormField::addPanel('Basic Information');
        $panel2 = FormField::addPanel('Billing');
        $panel3 = FormField::addPanel('Address');

        $panel5 = FormField::addPanel('SAML')->hideWhenCreating();
        $ssoEnabled = BooleanField::new('samlSettings.enabled', 'SSO Enabled')->hideWhenCreating();
        $disableNonSSO = BooleanField::new('samlSettings.disable_non_sso', 'Disable Non-SSO')->hideWhenCreating();

        $panel4 = FormField::addPanel('Additional Information');
        $id = IntegerField::new('id', 'ID');
        $numUsers = IntegerField::new('numUsers');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $logo, $name, $canceled, $email, $numUsers];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$panel1, $name, $dba, $username, $customDomain, $email, $country, $currency, $language, $panel2, $trialEnds, $billingProfile, $canceledAt, $panel3, $address1, $address2, $city, $state, $postalCode, $taxId, $type, $industry, $panel5, $ssoEnabled, $disableNonSSO, $panel4, $testMode, $fraud];
        }

        return [];
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if (Crud::PAGE_DETAIL === $responseParameters->get('pageName')) {
            /** @var Company $company */
            $company = $responseParameters->get('entity')->getInstance();

            $isMember = false;
            $isTempMember = false;
            if ($invoicedUser = $this->getInvoicedUser()) {
                $foundMember = $this->findCompanyMember($invoicedUser, $company);
                if ($foundMember) {
                    $isMember = !$foundMember->isExpired();
                    $isTempMember = $isMember ? $foundMember->getExpires() : false;
                }
            }

            $dashboardUrl = getenv('DASHBOARD_URL').'/?account='.$company->getId();

            $responseParameters->set('isMember', $isMember);
            $responseParameters->set('isTempMember', $isTempMember);
            $responseParameters->set('dashboardUrl', $dashboardUrl);

            // Load Company Details from Backend
            $companyDetails = $this->csAdminApiClient->request('/_csadmin/company_details', [
                'company_id' => $company->getId(),
            ]);
            $responseParameters->set('companyDetails', $companyDetails);

            // Load integrations
            $integrations = [];
            if (isset($companyDetails->integrations)) {
                foreach ($companyDetails->integrations as $integration) {
                    $link = null;

                    if ('intacct' == $integration->id) {
                        $link = $this->adminUrlGenerator
                            ->setController(IntacctSyncProfileCrudController::class)
                            ->setAction('detail')
                            ->setEntityId($company->getId())
                            ->generateUrl();
                    } elseif ('quickbooks_online' == $integration->id) {
                        $link = $this->adminUrlGenerator
                            ->setController(QuickBooksOnlineSyncProfileCrudController::class)
                            ->setAction('detail')
                            ->setEntityId($company->getId())
                            ->generateUrl();
                    } elseif ('xero' == $integration->id) {
                        $link = $this->adminUrlGenerator
                            ->setController(XeroSyncProfileCrudController::class)
                            ->setAction('detail')
                            ->setEntityId($company->getId())
                            ->generateUrl();
                    } elseif (in_array($integration->id, ['business_central', 'freshbooks', 'netsuite', 'quickbooks_desktop', 'sage_accounting', 'wave'])) {
                        $accountingSyncProfile = $this->getDoctrine()
                            ->getRepository(AccountingSyncProfile::class)
                            ->findOneBy([
                                'integration' => $integration->database_id,
                                'tenant' => $company,
                            ]);
                        if ($accountingSyncProfile) {
                            $link = $this->adminUrlGenerator
                                ->setController(AccountingSyncProfileCrudController::class)
                                ->setAction('detail')
                                ->setEntityId($accountingSyncProfile->getId())
                                ->generateUrl();
                        }
                    }

                    $integrations[] = [
                        'name' => $integration->name,
                        'link' => $link,
                    ];
                }
            }
            $this->adminUrlGenerator->unsetAll();
            usort($integrations, fn ($a, $b) => $a['name'] <=> $b['name']);
            $responseParameters->set('integrations', $integrations);

            // Find related Adyen Account
            $repository = $this->managerRegistry->getRepository(AdyenAccount::class);
            $adyenAccount = $repository->findOneBy(['tenant' => $company]);
            $responseParameters->set('adyenAccountId', $adyenAccount?->getId());
        }

        return $responseParameters;
    }

    public function joinCompany(AdminContext $context, string $apiLogEnvironment): Response
    {
        /** @var Company $company */
        $company = $context->getEntity()->getInstance();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $company);

        $invoicedUser = $this->getInvoicedUser();
        if (!$invoicedUser) {
            /** @var User $user */
            $user = $this->getUser();
            $this->addFlash('danger', 'You could not be added to this company because an Invoiced account for '.$user->getUsername().' does not exist. Please register for an Invoiced account at invoiced.com/signup and try again.');
        } elseif (!in_array($apiLogEnvironment, ['dev', 'staging']) && !$invoicedUser->twoFactorEnabled()) {
            $this->addFlash('danger', 'You could not be added to this company because you do not have two-factor authentication enabled. Please enable two-factor authentication and try again.');
        } else {
            $em = $this->getDoctrine()->getManager('Invoiced_ORM');

            /* Check if logged in user is member of the company */
            if ($existingMember = $this->findCompanyMember($invoicedUser, $company)) {
                $em->remove($existingMember);
                $em->flush();
            }

            /* Add company member */
            $newMember = new Member();
            $newMember->setTenant($company);
            $newMember->setUser($invoicedUser);
            $newMember->setExpires(CarbonImmutable::now()->addHour());
            $newMember->setRole('administrator');
            $em->persist($newMember);
            $em->flush();

            $this->addAuditEntry('join_company', (string) $company->getId());
        }

        return $this->redirect(
            $this->adminUrlGenerator->setAction('detail')
                ->generateUrl()
        );
    }

    public function leaveCompany(AdminContext $context): Response
    {
        /** @var Company $company */
        $company = $context->getEntity()->getInstance();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $company);

        /* Remove logged in user from company */
        $invoicedUser = $this->getInvoicedUser();
        if ($invoicedUser && $existingMember = $this->findCompanyMember($invoicedUser, $company)) {
            $em = $this->getDoctrine()->getManager('Invoiced_ORM');
            $em->remove($existingMember);
            $em->flush();
        }

        return $this->redirect(
            $this->adminUrlGenerator->setAction('detail')
                ->generateUrl()
        );
    }

    /**
     * Controls the extend trial of a company.
     */
    public function extendTrial(AdminContext $context, Request $request): Response
    {
        /** @var Company $company */
        $company = $context->getEntity()->getInstance();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('show', $company);

        $form = $this->createFormBuilder($company)
            ->add('trialEnds', DateTimeType::class)
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager('CustomerAdmin_ORM');
            $em->persist($company);
            $em->flush();

            $this->addAuditEntry('extend_trial', (string) $company->getId().'; trial_ends='.$company->getTrialEnds()->format('Y-m-d')); /* @phpstan-ignore-line */

            $this->addFlash('success', 'The trial for '.$company->getName().' has been extended');

            return $this->redirect(
                $this->adminUrlGenerator->setAction('detail')
                    ->generateUrl()
            );
        }

        return $this->render('customizations/actions/extend_trial.html.twig', [
            'companyId' => $company->getId(),
            'companyName' => $company->getName(),
            'form' => $form->createView(),
        ]);
    }

    /**
     * Controls the payment processing pricing of a company.
     */
    public function setPaymentPricing(AdminContext $context, Request $request): Response
    {
        /** @var Company $company */
        $company = $context->getEntity()->getInstance();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $company);

        $data = [
            'card_variable_fee' => .029,
            'card_international_added_variable_fee' => .01,
            'card_fixed_fee' => 0,
            'amex_interchange_variable_markup' => 0,
            'ach_variable_fee' => 0.008,
            'ach_max_fee' => 5,
            'ach_fixed_fee' => 0,
            'chargeback_fee' => 15,
        ];
        $form = $this->createFormBuilder($data)
            ->add('card_variable_fee', PercentType::class, [
                'label' => 'Variable Fee (%)',
                'required' => true,
                'scale' => 2,
                'constraints' => [
                    new NotBlank(),
                    new Range(['min' => 0, 'max' => 0.05]),
                    new GreaterThan(['value' => 0]),
                ],
            ])
            ->add('card_international_added_variable_fee', PercentType::class, [
                'label' => 'International Added Fee (%)',
                'required' => true,
                'scale' => 2,
                'constraints' => [
                    new NotBlank(),
                    new Range(['min' => 0, 'max' => 0.02]),
                ],
            ])
            ->add('card_fixed_fee', MoneyType::class, [
                'label' => 'Fixed Fee ($)',
                'required' => true,
                'currency' => strtoupper($company->getCurrency()),
                'constraints' => [
                    new NotBlank(),
                    new Range(['min' => 0, 'max' => 30.0]),
                ],
            ])
            ->add('card_interchange_passthrough', CheckboxType::class, [
                'label' => 'Interchange++ Pricing',
                'required' => false,
            ])
            ->add('amex_interchange_variable_markup', PercentType::class, [
                'label' => 'IC++ Variable Fee (%)',
                'required' => true,
                'scale' => 2,
                'constraints' => [
                    new NotBlank(),
                    new Range(['min' => 0, 'max' => 0.05]),
                ],
                'help' => 'This will create an Amex-specific Cost + Markup rule',
            ])
            ->add('ach_variable_fee', PercentType::class, [
                'label' => 'Variable Fee (%)',
                'required' => true,
                'scale' => 2,
                'constraints' => [
                    new NotBlank(),
                    new Range(['min' => 0, 'max' => 0.3]),
                ],
            ])
            ->add('ach_fixed_fee', MoneyType::class, [
                'label' => 'Fixed Fee ($)',
                'required' => true,
                'currency' => strtoupper($company->getCurrency()),
                'constraints' => [
                    new NotBlank(),
                    new Range(['min' => 0, 'max' => 30.0]),
                ],
            ])
            ->add('ach_max_fee', MoneyType::class, [
                'label' => 'Max Fee ($)',
                'required' => true,
                'currency' => strtoupper($company->getCurrency()),
                'constraints' => [
                    new NotBlank(),
                    new Range(['min' => 0, 'max' => 100.0]),
                ],
                'help' => 'If this is a fixed fee only, then set to $0',
            ])
            ->add('chargeback_fee', MoneyType::class, [
                'label' => 'Chargeback Fee ($)',
                'required' => true,
                'currency' => strtoupper($company->getCurrency()),
                'constraints' => [
                    new NotBlank(),
                    new Range(['min' => 0, 'max' => 50.0]),
                ],
            ])
            ->add('override_split_configuration_id', TextType::class, [
                'label' => 'Override Split Configuration',
                'required' => false,
                'help' => 'Apply a custom split configuration ID (optional)',
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $costPlus = (bool) $data['card_interchange_passthrough'];
            $cardInternationalFee = round($data['card_international_added_variable_fee'] * 100, 2);
            if ($costPlus) {
                // Cannot have an international fee with cost plus
                $cardInternationalFee = 0;
            }

            $achMaxFee = round($data['ach_max_fee'], 2) ?: null;
            $achFixedFee = round($data['ach_fixed_fee'], 2) ?: null;
            $achVariableFee = round($data['ach_variable_fee'] * 100, 2) ?: null;
            if ($achFixedFee && !$achVariableFee) {
                // Cannot have an ACH max fee on a fixed fee only pricing
                $achMaxFee = null;
            }

            $response = $this->csAdminApiClient->request('/_csadmin/set_payment_pricing', [
                'tenant_id' => $company->getId(),
                'card_variable_fee' => round($data['card_variable_fee'] * 100, 2),
                'card_international_added_variable_fee' => $cardInternationalFee,
                'card_fixed_fee' => round($data['card_fixed_fee'], 2) ?: null,
                'card_interchange_passthrough' => $costPlus,
                'amex_interchange_variable_markup' => round($data['amex_interchange_variable_markup'] * 100, 2) ?: null,
                'ach_variable_fee' => $achVariableFee,
                'ach_max_fee' => $achMaxFee,
                'ach_fixed_fee' => $achFixedFee,
                'chargeback_fee' => round($data['chargeback_fee'], 2),
                'override_split_configuration_id' => $data['override_split_configuration_id'] ?: null,
            ]);

            if (isset($response->error)) {
                $this->addFlash('danger', 'Setting pricing failed: '.$response->error);
            } else {
                $this->addAuditEntry('set_payment_pricing', $company->getId().'; '.http_build_query($data));
                $this->addFlash('success', 'The pricing for '.$company->getName().' has been set');

                return $this->redirect(
                    $this->adminUrlGenerator->setController(AdyenAccountCrudController::class)
                        ->setAction('detail')
                        ->setEntityId($response->adyen_account)
                        ->generateUrl()
                );
            }
        }

        return $this->render('customizations/actions/set_payment_pricing.html.twig', [
            'companyId' => $company->getId(),
            'companyName' => $company->getName(),
            'form' => $form->createView(),
        ]);
    }

    /**
     * Exports the data for a company.
     */
    public function exportData(AdminContext $context): Response
    {
        /** @var Company $company */
        $company = $context->getEntity()->getInstance();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $company);

        $user = $this->getInvoicedUser();
        $response = $this->csAdminApiClient->request('/_csadmin/export_data', [
            'company_id' => $company->getId(),
            'user_id' => $user?->getId(),
        ]);

        if (isset($response->error)) {
            $this->addFlash('danger', 'Exporting data failed: '.$response->error);
        } else {
            $this->addAuditEntry('export_data', (string) $company->getId());
            $this->addFlash('success', 'Data export for '.$company->getName().'. You will be notified by email when export will finish.');
        }

        return $this->redirect(
            $this->adminUrlGenerator->setAction('detail')
                ->generateUrl()
        );
    }

    /**
     * Marks a company fraudulent.
     */
    public function markCompanyFraudulent(AdminContext $context, Request $request): Response
    {
        /** @var Company $company */
        $company = $context->getEntity()->getInstance();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $company);

        if ('POST' == $request->getMethod()) {
            $response = $this->csAdminApiClient->request('/_csadmin/mark_fraudulent', [
                'company_id' => $company->getId(),
            ]);

            if (isset($response->error)) {
                $this->addFlash('danger', 'Marking company fraudulent failed: '.$response->error);
            } else {
                $this->addAuditEntry('mark_fraudulent', (string) $company->getId());
                $this->addFlash('success', 'Successfully marked this account as fraudulent: '.$company->getName());
            }

            return $this->redirect(
                $this->adminUrlGenerator->setAction('detail')
                    ->generateUrl()
            );
        }

        return $this->render('customizations/actions/mark_fraudulent.html.twig', [
            'companyId' => $company->getId(),
            'companyName' => $company->getName(),
        ]);
    }

    public function cancelCompany(AdminContext $context): Response
    {
        /** @var Company $company */
        $company = $context->getEntity()->getInstance();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $company);

        $company->setCanceled(true);
        $company->setCanceledAt(CarbonImmutable::now());
        $em = $this->getDoctrine()->getManager('Invoiced_ORM');
        $em->persist($company);
        $em->flush();

        $this->addFlash('success', 'Successfully canceled the account for '.$company->getName());

        return $this->redirect(
            $this->adminUrlGenerator->setAction('detail')
                ->generateUrl()
        );
    }

    public function reactivateCompany(AdminContext $context): Response
    {
        /** @var Company $company */
        $company = $context->getEntity()->getInstance();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $company);

        $company->setCanceled(false);
        $company->setCanceledAt(null);
        $em = $this->getDoctrine()->getManager('Invoiced_ORM');
        $em->persist($company);
        $em->flush();

        $this->addFlash('success', 'Successfully reactivated the account for '.$company->getName());

        return $this->redirect(
            $this->adminUrlGenerator->setAction('detail')
                ->generateUrl()
        );
    }

    public function cancelSubscription(AdminContext $context, Request $request): Response
    {
        /** @var Company $company */
        $company = $context->getEntity()->getInstance();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $company);

        if ('POST' == $request->getMethod()) {
            $atPeriodEnd = '1' === $request->request->get('at_period_end');
            $response = $this->csAdminApiClient->request('/_csadmin/cancel_account', [
                'company_id' => $company->getId(),
                'at_period_end' => $atPeriodEnd,
            ]);

            if (isset($response->error)) {
                $this->addFlash('danger', 'Canceling company failed: '.$response->error);
            } elseif ($atPeriodEnd) {
                $this->addAuditEntry('cancel_company', (string) $company->getId().'; at_period_end=true');
                $this->addFlash('success', 'The account for '.$company->getName().' will be canceled after this billing period');
            } else {
                $this->addAuditEntry('cancel_company', (string) $company->getId());
                $this->addFlash('success', 'Successfully canceled the account for '.$company->getName());
            }

            return $this->redirect(
                $this->adminUrlGenerator->setAction('detail')
                    ->generateUrl()
            );
        }

        return $this->render('customizations/actions/cancel_account.html.twig', [
            'companyId' => $company->getId(),
            'companyName' => $company->getName(),
            'billingSystem' => $company->getBillingSystemName(),
            'billingSystemId' => $company->getBillingSystemId(),
        ]);
    }

    private function getInvoicedUser(): ?InvUser
    {
        /* Get instances of the InvUser and CsUser entities of the person logged into the CS app */
        /** @var User $user */
        $user = $this->getUser();

        return $this->getDoctrine()
            ->getRepository(InvUser::class)
            ->findOneBy(['email' => $user->getEmail()]);
    }

    /**
     * Checks if current logged in admin is a member of a company given the id.
     * Used when adding the CsUser to the company.
     */
    private function findCompanyMember(InvUser $user, Company $company): ?Member
    {
        return $this->getDoctrine()
            ->getRepository(Member::class)
            ->findOneBy([
                'user_id' => $user->getId(),
                'tenant_id' => $company->getId(),
            ]);
    }
}
