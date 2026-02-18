<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\AdyenAccount;
use App\Service\CsAdminApiClient;
use CommerceGuys\Addressing\Address;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Formatter\DefaultFormatter;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Throwable;

class AdyenAccountCrudController extends AbstractCrudController
{
    use CrudControllerTrait;
    use ReturnToCompanyTrait;

    public function __construct(
        private CsAdminApiClient $csAdminApiClient,
        private AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AdyenAccount::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('Flywire Payments Accounts')
            ->setEntityLabelInSingular('Flywire Payments Account')
            ->setSearchFields(['id', 'tenant.id', 'tenant.name', 'account_holder_id', 'reference'])
            ->setDefaultSort(['id' => 'DESC'])
            ->overrideTemplate('crud/detail', 'customizations/show/adyen_account_show.html.twig')
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->disable('new')
            ->disable('edit')
            ->disable('delete')
            ->remove('detail', 'index');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('id')
            ->add(TextFilter::new('account_holder_id', 'Account Holder ID'))
            ->add(TextFilter::new('reference', 'Reference'))
            ->add(TextFilter::new('industry_code', 'Industry Code'))
            ->add(NumericFilter::new('pricing_configuration_id', 'Pricing Configuration ID'))
            ->add(BooleanFilter::new('has_onboarding_problem', 'Has Onboarding Problem'));
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IntegerField::new('id');
        $tenantId = IntegerField::new('tenant.id', 'Tenant ID');
        $companyName = TextField::new('tenant.name', 'Company Name');
        $reference = TextField::new('reference', 'Reference');
        $accountHolder = TextField::new('accountHolderId', 'Account Holder ID');
        $industryCode = TextField::new('industryCode', 'Industry Code');
        $pricingConfiguration = IntegerField::new('pricingConfigurationId', 'Pricing Configuration ID');
        $onboardingProblem = BooleanField::new('hasOnboardingProblem', 'Has Onboarding Problem')
            ->renderAsSwitch(false);

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $tenantId, $companyName, $reference, $accountHolder, $industryCode, $pricingConfiguration, $onboardingProblem];
        }

        return [];
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if (Crud::PAGE_DETAIL === $responseParameters->get('pageName')) {
            /** @var AdyenAccount $adyenAccount */
            $adyenAccount = $responseParameters->get('entity')->getInstance();
            $data = $this->csAdminApiClient->request('/_csadmin/adyen_account', [
                'account_id' => $adyenAccount->getId(),
            ]);

            $adyenAccountData = json_decode((string) json_encode($data), true);
            if (isset($adyenAccountData['legalEntity']['organization']['registeredAddress'])) {
                $adyenAccountData['legalEntity']['organization']['address'] = $this->formatAddress($adyenAccountData['legalEntity']['organization']['registeredAddress']);
            }

            $responseParameters->set('adyenAccountData', $adyenAccountData); // convert to array
            $responseParameters->set('problems', $this->makeProblems($adyenAccountData));
        }

        return $responseParameters;
    }

    private function makeProblems(array $adyenAccountData): array
    {
        if (!isset($adyenAccountData['accountHolder']['capabilities'])) {
            return [];
        }

        $problems = [];
        $alreadySeen = [];

        foreach ($adyenAccountData['accountHolder']['capabilities'] as $capability) {
            if (!isset($capability['problems'])) {
                continue;
            }

            foreach ($capability['problems'] as $problem) {
                $entityId = $problem['entity']['id'];
                $key = $entityId;

                foreach ($problem['verificationErrors'] as $verificationError) {
                    $key2 = $key.$verificationError['code'];
                    if (isset($alreadySeen[$key2])) {
                        continue;
                    }
                    $alreadySeen[$key2] = true;

                    $verificationError['entityName'] = $this->getEntityName($adyenAccountData, $entityId);
                    $verificationError['subErrors'] ??= [];

                    $problems[] = $verificationError;
                }
            }
        }

        return $problems;
    }

    private function getEntityName(array $adyenAccountData, string $id): string
    {
        if ($adyenAccountData['legalEntity']['id'] == $id && isset($adyenAccountData['legalEntity']['organization']['legalName'])) {
            return $adyenAccountData['legalEntity']['organization']['legalName'];
        }

        foreach ($adyenAccountData['legalEntity']['entityAssociations'] as $association) {
            if ($association['legalEntityId'] === $id) {
                return $association['name'];
            }
        }

        return $id;
    }

    private function formatAddress(array $address): string
    {
        try {
            $address = (new Address())
                ->withAddressLine1((string) ($address['street'] ?? ''))
                ->withAddressLine2((string) ($address['street2'] ?? ''))
                ->withLocality((string) ($address['city'] ?? ''))
                ->withAdministrativeArea((string) ($address['stateOrProvince'] ?? ''))
                ->withPostalCode((string) ($address['postalCode'] ?? ''))
                ->withCountryCode((string) ($address['country'] ?? ''));
            $addressFormatRepository = new AddressFormatRepository();
            $countryRepository = new CountryRepository();
            $subdivisionRepository = new SubdivisionRepository();
            $formatter = new DefaultFormatter($addressFormatRepository, $countryRepository, $subdivisionRepository);

            return $formatter->format($address, ['html' => false]);
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * Controls the statement descriptor.
     */
    public function setStatementDescriptor(AdminContext $context, Request $request): Response
    {
        /** @var AdyenAccount $adyenAccount */
        $adyenAccount = $context->getEntity()->getInstance();
        $company = $adyenAccount->getTenant();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $company);

        $data = [
            'statement_descriptor' => $company->getName(),
        ];
        $form = $this->createFormBuilder($data)
            ->add('statement_descriptor', TextType::class, [
                'label' => 'Statement Descriptor',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 1, max: 22),
                ],
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $response = $this->csAdminApiClient->request('/_csadmin/set_statement_descriptor', [
                'tenant_id' => $company->getId(),
                'statement_descriptor' => $data['statement_descriptor'],
            ]);

            if (isset($response->error)) {
                $this->addFlash('danger', 'Setting statement descriptor failed: '.$response->error);
            } else {
                $this->addAuditEntry('set_statement_descriptor', $company->getId().'; '.http_build_query($data));
                $this->addFlash('success', 'The statement descriptor for '.$company->getName().' has been set');

                return $this->redirect(
                    $this->adminUrlGenerator->setController(AdyenAccountCrudController::class)
                        ->setAction('detail')
                        ->setEntityId($response->adyen_account)
                        ->generateUrl()
                );
            }
        }

        return $this->render('customizations/actions/set_statement_descriptor.html.twig', [
            'companyId' => $company->getId(),
            'companyName' => $company->getName(),
            'form' => $form->createView(),
        ]);
    }

    /**
     * Controls the top up threshold - number of days.
     */
    public function setTopUpThresholdNumberOfDays(AdminContext $context, Request $request): Response
    {
        /** @var AdyenAccount $adyenAccount */
        $adyenAccount = $context->getEntity()->getInstance();
        $company = $adyenAccount->getTenant();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $company);

        $data = [
            'top_up_threshold_num_of_days' => $company->getMerchantAccounts()[0]->getTopUpThresholdNumOfDays(),
        ];
        $form = $this->createFormBuilder($data)
            ->add('top_up_threshold_num_of_days', TextType::class, [
                'label' => 'Top Up Threshold Number Of Days',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $response = $this->csAdminApiClient->request('/_csadmin/set_top_up_threshold_num_of_days', [
                'tenant_id' => $company->getId(),
                'top_up_threshold_num_of_days' => $data['top_up_threshold_num_of_days'],
            ]);

            if (isset($response->error)) {
                $this->addFlash('danger', 'Setting top up threshold num of days failed: '.$response->error);
            } else {
                $this->addAuditEntry('set_top_up_threshold_num_of_days', $company->getId().'; '.http_build_query($data));
                $this->addFlash('success', 'The top up threshold num of days for '.$company->getName().' has been set');

                return $this->redirect(
                    $this->adminUrlGenerator->setController(AdyenAccountCrudController::class)
                        ->setAction('detail')
                        ->setEntityId($response->adyen_account)
                        ->generateUrl()
                );
            }
        }

        return $this->render('customizations/actions/set_top_up_threshold_num_of_days.html.twig', [
            'companyId' => $company->getId(),
            'companyName' => $company->getName(),
            'form' => $form->createView(),
        ]);
    }
}
