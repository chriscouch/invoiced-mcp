<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\MerchantAccount;
use App\Service\CsAdminApiClient;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class MerchantAccountCrudController extends AbstractCrudController
{
    use CrudControllerTrait;
    use ReturnToCompanyTrait;

    /**
     * This includes all previously supported payment gateways. Some may no longer be supported.
     */
    private const GATEWAYS = [
        'Affinipay' => 'affinipay',
        'American Express' => 'amex',
        'Authorize.Net' => 'authorizenet',
        'Bluepay' => 'bluepay',
        'Braintree' => 'braintree',
        'CPACharge' => 'cpacharge',
        'CardKnox' => 'cardknox',
        'Chase Paymentech Orbital' => 'orbital',
        'CyberSource' => 'cybersource',
        'Flywire (MoR)' => 'flywire',
        'Flywire Payments (Payfac)' => 'flywire_payments',
        'GoCardless' => 'gocardless',
        'Intuit QuickBooks Payments' => 'intuit',
        'Invoiced Payments' => 'invoiced',
        'LawPay' => 'lawpay',
        'Mock' => 'mock',
        'Moneris' => 'moneris',
        'NACHA File' => 'nacha',
        'NMI' => 'nmi',
        'OPP' => 'OPP',
        'PayPal Payflow Pro' => 'payflowpro',
        'Stripe' => 'stripe',
        'Test' => 'test',
        'USAePay' => 'usaepay',
        'Worldpay (Merchant Partners)' => 'worldpay_mp',
        'Worldpay (Vantiv)' => 'vantiv',
    ];

    private const ALLOWED_NEW_GATEWAYS = [
        'Authorize.Net' => 'authorizenet',
        'Braintree' => 'braintree',
        'Cardknox' => 'cardknox',
        'Chase Paymentech Orbital' => 'orbital',
        'CyberSource' => 'cybersource',
        'Flywire (MoR)' => 'flywire',
        'GoCardless' => 'gocardless',
        'NACHA File' => 'nacha',
        'NMI' => 'nmi',
        'OPP' => 'OPP',
        'PayPal Payflow Pro' => 'payflowpro',
        'Stripe' => 'stripe',
    ];

    private CsAdminApiClient $csAdminApiClient;
    private RequestStack $requestStack;

    public function __construct(CsAdminApiClient $csAdminApiClient, RequestStack $requestStack)
    {
        $this->csAdminApiClient = $csAdminApiClient;
        $this->requestStack = $requestStack;
    }

    public static function getEntityFqcn(): string
    {
        return MerchantAccount::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('Gateway Configurations')
            ->setEntityLabelInSingular('Gateway Configuration')
            ->setSearchFields(['id', 'tenant_id', 'gateway', 'gateway_id', 'name'])
            ->setDefaultSort(['id' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('New Gateway Configuration');
            })
            ->disable('delete');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('id')
            ->add('gateway')
            ->add('gateway_id');
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IntegerField::new('id');
        $tenantId = IntegerField::new('tenant_id');
        $gateway = ChoiceField::new('gateway')->setChoices(self::GATEWAYS);
        $gatewayNew = ChoiceField::new('gateway')->setChoices(self::ALLOWED_NEW_GATEWAYS);
        $gatewayId = TextField::new('gateway_id')
            ->formatValue(function ($value) {
                if (str_starts_with((string) $value, 'acct_')) {
                    return '<a href="https://dashboard.stripe.com/connect/accounts/'.$value.'" target="_blank">'.$value.'</a>';
                }

                return $value;
            });
        $key = TextField::new('key');
        $accessToken = TextField::new('accessToken')->setFormType(PasswordType::class);

        $name = TextField::new('name');
        $createdAt = DateTimeField::new('created_at', 'Date Created');
        $updatedAt = DateTimeField::new('updated_at', 'Last Updated');
        $deleted = BooleanField::new('deleted', 'Deleted');
        $deletedAt = DateTimeField::new('deleted_at', 'Date Deleted');
        $tenant = AssociationField::new('tenant');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $tenant, $gateway, $gatewayId, $name];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $gateway, $gatewayId, $tenant, $createdAt, $updatedAt, $deleted, $deletedAt];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$tenantId, $name, $gatewayNew, $key, $accessToken];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$name, $deleted];
        }

        return [];
    }

    public function createEntity(string $entityFqcn): MerchantAccount
    {
        $account = new MerchantAccount();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();
        $tenantId = (int) $request->query->get('tenant_id');
        if ($tenantId > 0) {
            $account->setTenantId($tenantId);
        }

        return $account;
    }

    /**
     * @param MerchantAccount $entityInstance
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $tenantId = $entityInstance->getTenantId();
        $gateway = $entityInstance->getGateway();
        $data = [
            'tenant_id' => $tenantId,
            'gateway' => $gateway,
            'name' => $entityInstance->getName(),
        ];
        if ("OPP" === $gateway) {
            $data['credentials'] = [
                'key' => $entityInstance->getKey(),
                'accessToken' => $entityInstance->getAccessToken(),
            ];
        }

        $response = $this->csAdminApiClient->request('/_csadmin/new_merchant_account', $data);

        if (isset($response->error)) {
            $this->addFlash('danger', 'Saving merchant account failed: '.$response->error);
        } else {
            $this->addAuditEntry('new_merchant_account', (string) $tenantId);
            $this->addFlash('success', 'Merchant account created for '.(string) $tenantId);
        }
    }

    /**
     * @param MerchantAccount $entityInstance
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $tenantId = $entityInstance->getTenantId();
        if ($entityInstance->getDeleted()) {
            $this->addFlash('warning', 'Saving merchant account ignored');

            return;
        }

        $response = $this->csAdminApiClient->request('/_csadmin/merchant_accounts/'.$entityInstance->getId().'/deleted', [
            'tenant_id' => $tenantId,
        ]);

        if (isset($response->error)) {
            $this->addFlash('danger', 'Saving merchant account failed: '.$response->error);
        } else {
            $this->addAuditEntry('edit_merchant_account', $tenantId.':'.$entityInstance->getId());
            $this->addFlash('success', 'Merchant account undeleted for '.$tenantId);
        }
    }
}
