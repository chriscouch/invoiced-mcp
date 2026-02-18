<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\User;
use App\Service\CsAdminApiClient;
use App\Service\IpInfoLookup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AvatarField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class UserCrudController extends AbstractCrudController
{
    use CrudControllerTrait;

    public function __construct(private IpInfoLookup $ipInfoLookup)
    {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Users')
            ->setSearchFields(['support_pin', 'id', 'first_name', 'last_name', 'email', 'default_company.name', 'default_company.id', 'companyMembers.id'])
            ->setDefaultSort(['id' => 'ASC'])
            ->overrideTemplate('crud/detail', 'customizations/show/user_show.html.twig')
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $emailLogs = Action::new('emailLogs', 'Email Logs', 'fa fa-at')
            ->linkToRoute('email_log_search', fn (User $user) => ['email' => $user->getEmail()]);
        $resetLoginCounter = Action::new('resetLoginCounter', 'Reset Login Counter', 'fa fa-unlock-alt')
            ->linkToCrudAction('resetLoginCounter');
        $resetPassword = Action::new('getPasswordResetLink', 'Reset Password', 'fa fa-key')
            ->linkToCrudAction('getPasswordResetLink');
        $disable2fa = Action::new('disable2fa', 'Disable 2FA', 'fa fa-mobile')
            ->linkToCrudAction('disable2fa')
            ->displayIf(fn ($user) => $user->isVerified2fa());

        return $actions
            ->add('index', 'detail')
            ->remove('detail', 'index')
            ->add('detail', $resetPassword)
            ->setPermission('getPasswordResetLink', 'edit')
            ->add('detail', $resetLoginCounter)
            ->setPermission('resetLoginCounter', 'edit')
            ->add('detail', $disable2fa)
            ->setPermission('disable2fa', 'edit')
            ->add('detail', $emailLogs)
            ->setPermission('emailLogs', 'edit')
            ->disable('delete', 'new');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('support_pin', 'Support PIN'))
            ->add('id')
            ->add('first_name')
            ->add('last_name')
            ->add('email')
            ->add('enabled')
            ->add(TextFilter::new('ip', 'IP Address'));
    }

    public function configureFields(string $pageName): iterable
    {
        $email = TextField::new('email')->setColumns(6);
        $firstName = TextField::new('first_name')->setColumns(6);
        $lastName = TextField::new('last_name')->setColumns(6);
        $ip = TextField::new('ip', 'IP Address')
            ->formatValue([$this->ipInfoLookup, 'makeIpInfoLink']);
        $enabled = Field::new('enabled');
        $createdAt = DateTimeField::new('created_at', 'Date Created');
        $updatedAt = DateTimeField::new('updated_at', 'Last Updated');
        $googleClaimedId = Field::new('google_claimed_id', 'Google Claimed Id');
        $intuitClaimedId = Field::new('intuit_claimed_id', 'Intuit Claimed ID');
        $microsoftClaimedId = Field::new('microsoft_claimed_id', 'Microsoft Claimed ID');
        $xeroClaimedId = Field::new('xero_claimed_id', 'Xero Claimed ID');
        $authyId = Field::new('authy_id', 'Authy Id');
        $supportPin = Field::new('support_pin', 'Support PIN');
        $defaultCompany = AssociationField::new('default_company');
        $companyMembers = AssociationField::new('companyMembers', 'All Companies')->setTemplatePath('customizations/fields/user_company.html.twig');
        $canceledCompanies = AssociationField::new('canceledCompanies', 'Canceled Companies')->setTemplatePath('customizations/fields/canceled_companies.html.twig');
        $accountSecurityEvents = AssociationField::new('accountSecurityEvents', 'Latest Account Security Events')->setTemplatePath('customizations/fields/user_account_security_event.html.twig');
        $id = IntegerField::new('id', 'ID');
        $avatarEmail = AvatarField::new('avatarEmail')
            ->setIsGravatarEmail(true);
        $disableIpCheck = BooleanField::new('disable_ip_check', 'Disable IP Check');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $avatarEmail, $firstName, $lastName, $email, $defaultCompany];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [FormField::addPanel('User Information'), $firstName, $lastName, $email, $ip, FormField::addPanel('Additional Information'), $supportPin, $googleClaimedId, $intuitClaimedId, $microsoftClaimedId, $xeroClaimedId, $authyId, $disableIpCheck, $createdAt, $updatedAt, $accountSecurityEvents, FormField::addPanel('Associated Companies'), $defaultCompany, $companyMembers, $canceledCompanies];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [FormField::addPanel('User Information'), $firstName, $lastName, $email, FormField::addPanel('Additional Information'), $enabled, $disableIpCheck];
        }

        return [];
    }

    /**
     * Resets the failed login counter for the given user.
     */
    public function resetLoginCounter(AdminContext $context, AdminUrlGenerator $adminUrlGenerator, CsAdminApiClient $csAdminApiClient): Response
    {
        /** @var User $user */
        $user = $context->getEntity()->getInstance();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $user);

        $response = $csAdminApiClient->request('/_csadmin/reset_login_counter', [
            'user_id' => $user->getId(),
        ]);

        if (isset($response->error)) {
            $this->addFlash('danger', 'Resetting failed login counter failed: '.$response->error);
        } else {
            $this->addAuditEntry('reset_login_counter', (string) $user->getId());
            $this->addFlash('success', 'Reset failed login counter for '.(string) $user);
        }

        return $this->redirect(
            $adminUrlGenerator->setAction('detail')
                ->generateUrl()
        );
    }

    /**
     * Returns a password reset link for the given user.
     */
    public function getPasswordResetLink(AdminContext $context, AdminUrlGenerator $adminUrlGenerator, CsAdminApiClient $csAdminApiClient): Response
    {
        /** @var User $user */
        $user = $context->getEntity()->getInstance();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $user);

        $response = $csAdminApiClient->request('/_csadmin/reset_password', [
            'user_id' => $user->getId(),
        ]);

        if (isset($response->error)) {
            $this->addFlash('danger', 'Generating reset password link failed: '.$response->error);
        } else {
            $this->addAuditEntry('reset_password_link', (string) $user->getId());
            $this->addFlash('success', 'Reset password link for '.(string) $user.' (link valid for 4 hours): '.$response->link);
        }

        return $this->redirect(
            $adminUrlGenerator->setAction('detail')
                ->generateUrl()
        );
    }

    /**
     * Disables 2FA for the given user.
     */
    public function disable2fa(AdminContext $context, AdminUrlGenerator $adminUrlGenerator): Response
    {
        /** @var User $user */
        $user = $context->getEntity()->getInstance();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $user);

        $em = $this->getDoctrine()
            ->getManager('Invoiced_ORM');
        $user->setVerified2fa(false);
        $user->setAuthyId(null);
        $em->persist($user);
        $em->flush();

        $this->addAuditEntry('disable_2fa', (string) $user->getId());
        $this->addFlash('success', 'Disabled 2FA for '.(string) $user);

        return $this->redirect(
            $adminUrlGenerator->setAction('detail')
                ->generateUrl()
        );
    }
}
