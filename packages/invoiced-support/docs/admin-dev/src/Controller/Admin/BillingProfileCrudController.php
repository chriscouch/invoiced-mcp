<?php

namespace App\Controller\Admin;

use App\Entity\CustomerAdmin\Order;
use App\Entity\Invoiced\BillingProfile;
use App\Enums\BillingSystem;
use App\Service\CsAdminApiClient;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BillingProfileCrudController extends AbstractCrudController
{
    use CrudControllerTrait;

    private const BILLING_SYSTEMS = [
        'None' => null,
        'Invoiced' => 'invoiced',
        'Reseller' => 'reseller',
        'Stripe' => 'stripe',
    ];

    public const BILLING_INTERVALS = [
        'None' => null,
        'Monthly' => 1,
        'Yearly' => 2,
        'Quarterly' => 3,
        'Semiannually' => 4,
    ];

    public function __construct(
        private string $apiLogEnvironment,
        private CsAdminApiClient $csAdminApiClient,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return BillingProfile::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Billing Profiles')
            ->setSearchFields(['id', 'name', 'invoiced_customer', 'stripe_customer'])
            ->setDefaultSort(['id' => 'DESC'])
            ->overrideTemplate('crud/detail', 'customizations/show/billing_profile_show.html.twig')
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $newCustomer = Action::new('newCustomer', 'New Customer')
            ->createAsGlobalAction()
            ->addCssClass('btn btn-primary')
            ->linkToRoute('new_customer_form');

        return $actions
            ->add('index', 'detail')
            ->add('index', $newCustomer)
            ->remove('detail', 'index')
            ->disable('delete');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('billing_system', 'Billing System')->setChoices(self::BILLING_SYSTEMS))
            ->add(ChoiceFilter::new('billing_interval', 'Billing Interval')->setChoices(self::BILLING_INTERVALS))
            ->add(TextFilter::new('invoiced_customer', 'Invoiced Customer ID'))
            ->add(TextFilter::new('stripe_customer', 'Stripe Customer ID'))
            ->add(TextFilter::new('referred_by', 'Referred By'));
    }

    public function configureFields(string $pageName): iterable
    {
        $invoicedDomain = match ($this->apiLogEnvironment) {
            'sandbox', 'staging', 'dev' => 'https://app.sandbox.invoiced.com',
            default => 'https://app.invoiced.com',
        };

        $name = TextField::new('name', 'Name');
        $billingSystem = ChoiceField::new('billing_system', 'Billing System')->setChoices(self::BILLING_SYSTEMS);
        $billingSystemText = TextField::new('billingSystemName', 'Billing System');
        $invoicedCustomer = TextField::new('invoiced_customer', 'Invoiced Customer ID')
            ->formatValue(function ($value) use ($invoicedDomain) {
                if (!$value) {
                    return '<span class="badge badge-secondary">Null</span>';
                }

                return '<a href="'.$invoicedDomain.'/customers/'.$value.'" target="_blank">'.$value.'</a>';
            });
        $stripeCustomer = TextField::new('stripe_customer', 'Stripe Customer ID')
            ->formatValue(function ($value) {
                if (!$value) {
                    return '<span class="badge badge-secondary">Null</span>';
                }

                return '<a href="https://dashboard.stripe.com/customers/'.$value.'" target="_blank">'.$value.'</a>';
            });
        $pastDue = Field::new('past_due', 'Past Due');
        $referredBy = TextField::new('referred_by', 'Referred By')
            ->setRequired(false);
        $id = IntegerField::new('id', 'ID');
        $createdAt = DateTimeField::new('created_at', 'Date Created');
        $updatedAt = DateTimeField::new('updated_at', 'Last Updated');
        $billingInterval = ChoiceField::new('billing_interval', 'Billing Interval')->setChoices(self::BILLING_INTERVALS);
        $billingIntervalText = TextField::new('billingIntervalName', 'Billing Interval');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $name, $billingSystemText, $invoicedCustomer, $stripeCustomer, $billingIntervalText];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$name, $billingSystem, $invoicedCustomer, $stripeCustomer, $referredBy, $billingInterval];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$name, $billingSystem, $invoicedCustomer, $stripeCustomer, $referredBy, $billingInterval, $pastDue];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$billingSystemText, $invoicedCustomer, $stripeCustomer, $referredBy, $billingIntervalText, $pastDue, $createdAt, $updatedAt];
        }

        return [];
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if (Crud::PAGE_DETAIL === $responseParameters->get('pageName')) {
            /** @var BillingProfile $billingProfile */
            $billingProfile = $responseParameters->get('entity')->getInstance();

            // Orders
            $repository = $this->getDoctrine()->getRepository(Order::class);
            $orders = $repository->findBy([
                'billingProfileId' => $billingProfile->getId(),
            ]);
            $responseParameters->set('orders', $orders);

            // Load Billing Profile Details from Backend
            $billingProfileDetails = $this->csAdminApiClient->request('/_csadmin/billing_profile_details', [
                'billing_profile_id' => $billingProfile->getId(),
            ]);
            $responseParameters->set('billingProfileDetails', $billingProfileDetails);
        }

        return $responseParameters;
    }

    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        return parent::createNewFormBuilder($entityDto, $formOptions, $context)
            ->addEventListener(FormEvents::POST_SUBMIT, [self::class, 'validateBillingProfile']);
    }

    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        return parent::createEditFormBuilder($entityDto, $formOptions, $context)
            ->addEventListener(FormEvents::POST_SUBMIT, [self::class, 'validateBillingProfile']);
    }

    public static function validateBillingProfile(FormEvent $event): void
    {
        /** @var BillingProfile $billingProfile */
        $billingProfile = $event->getData();
        $form = $event->getForm();
        $billingSystem = $billingProfile->getBillingSystemEnum();
        if (BillingSystem::Invoiced == $billingSystem && !$billingProfile->getInvoicedCustomer()) {
            $form->addError(new FormError('Missing Invoiced Customer ID'));
        }

        if (BillingSystem::Reseller == $billingSystem && !$billingProfile->getInvoicedCustomer()) {
            $form->addError(new FormError('Missing Invoiced Customer ID'));
        }

        if (BillingSystem::Stripe == $billingSystem && !$billingProfile->getStripeCustomer()) {
            $form->addError(new FormError('Missing Stripe Customer ID'));
        }
    }

    /**
     * Generates dispute evidence for a billing profile.
     */
    public function disputeEvidence(AdminContext $context, AdminUrlGenerator $adminUrlGenerator): Response
    {
        /** @var BillingProfile $billingProfile */
        $billingProfile = $context->getEntity()->getInstance();

        $response = $this->csAdminApiClient->request('/_csadmin/dispute_evidence', [
            'billing_profile_id' => $billingProfile->getId(),
        ]);

        if (isset($response->error)) {
            $this->addFlash('danger', 'Generating dispute evidence failed: '.$response->error);
        } else {
            $this->addAuditEntry('dispute_evidence', (string) $billingProfile->getId());
            $this->addFlash('success', 'Download dispute evidence: '.$response->url);
        }

        return $this->redirect(
            $adminUrlGenerator->setAction('detail')
                ->generateUrl()
        );
    }

    /**
     * Responds to a dispute for a billing profile.
     */
    public function respondToDispute(AdminContext $context, AdminUrlGenerator $adminUrlGenerator, Request $request): Response
    {
        /** @var BillingProfile $billingProfile */
        $billingProfile = $context->getEntity()->getInstance();

        $form = $this->createFormBuilder()
            ->add('stripeDisputeId', TextType::class, [
                'label' => 'Stripe Dispute ID',
                'required' => true,
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $response = $this->csAdminApiClient->request('/_csadmin/respond_to_dispute', [
                'billing_profile_id' => $billingProfile->getId(),
                'stripe_dispute_id' => $form->get('stripeDisputeId')->getData(),
            ]);

            if (isset($response->error)) {
                $form->addError(new FormError('Responding to dispute failed: '.$response->error));
            } else {
                $this->addAuditEntry('respond_to_dispute', (string) $billingProfile->getId());
                $this->addFlash('success', 'Dispute response was submitted');

                return $this->redirect(
                    $adminUrlGenerator->setAction('detail')
                        ->generateUrl()
                );
            }
        }

        return $this->render('customizations/actions/respond_to_dispute.html.twig', [
            'billingProfileName' => $billingProfile->getName(),
            'form' => $form->createView(),
        ]);
    }
}
