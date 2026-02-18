<?php

namespace App\Controller\Admin;

use App\Entity\CustomerAdmin\Order;
use App\Entity\CustomerAdmin\User as CsUser;
use App\Entity\Invoiced\BillingProfile;
use App\Entity\Invoiced\Company;
use App\Entity\Invoiced\PurchasePageContext;
use App\Entity\Invoiced\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class DashboardController extends AbstractDashboardController
{
    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(AdminUrlGenerator $adminUrlGenerator)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    #[Route(path: '/admin', name: 'admin_home')]
    public function index(): Response
    {
        $url = $this->adminUrlGenerator
            ->setController(CompanyCrudController::class)
            ->setAction('index')
            ->generateUrl();

        return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle(getenv('API_LOG_ENV') ?: 'dev')
            ->setFaviconPath('favicon.ico');
    }

    public function configureAssets(): Assets
    {
        $assets = parent::configureAssets();

        return $assets->addCssFile('build/css/easyadmin.css')
            ->addJsFile('js/jquery.min.js');
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->showEntityActionsInlined();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::section('Sales');
        yield MenuItem::linkToCrud('Billing Profiles', 'fas fa-sitemap', BillingProfile::class);
        if ($this->isGranted('ROLE_SUPER_ADMIN') || $this->isGranted('ROLE_CUSTOMER_SUPPORT') || $this->isGranted('ROLE_SALES')) {
            yield MenuItem::linkToCrud('Purchase Pages', 'fas fa-cart-plus', PurchasePageContext::class);
        }
        yield MenuItem::linkToCrud('Orders', 'fas fa-shopping-cart', Order::class);

        yield MenuItem::section('Browse');
        yield MenuItem::linkToCrud('Companies', 'fas fa-building', Company::class);
        yield MenuItem::linkToCrud('Users', 'fas fa-user', User::class);
        yield MenuItem::linkToRoute('Objects', 'fas fa-sitemap', 'entity_list');

        if ($this->isGranted('ROLE_SUPER_ADMIN') || $this->isGranted('ROLE_CUSTOMER_SUPPORT')) {
            yield MenuItem::section('Tools');
            yield MenuItem::linkToRoute('API Logs', 'fas fa-terminal', 'api_log_viewer');
            yield MenuItem::linkToRoute('Email Logs', 'fas fa-at', 'email_log_viewer');
            yield MenuItem::linkToRoute('Payment Logs', 'fas fa-cash-register', 'payment_log_viewer');
            yield MenuItem::linkToRoute('Integration Logs', 'fas fa-network-wired', 'integration_log_viewer');
        }

        if ($this->isGranted('ROLE_SUPER_ADMIN') || $this->isGranted('ROLE_CUSTOMER_SUPPORT') || $this->isGranted('ROLE_MARKETING')) {
            yield MenuItem::linkToRoute('SQL Console', 'fas fa-database', 'sql_console');
        }

        yield MenuItem::section('Reports')->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToRoute('Monthly Report', 'far fa-file-alt', 'bi_report_form')->setPermission('ROLE_SUPER_ADMIN');

        yield MenuItem::subMenu('Environment: '.(getenv('API_LOG_ENV') ?: 'dev'))->setCssClass('text-muted mt-4');
    }

    /**
     * @param CsUser $user
     */
    public function configureUserMenu(UserInterface $user): UserMenu
    {
        $menu = parent::configureUserMenu($user);

        $menu->setGravatarEmail($user->getUsername());

        $menuItems = [
            MenuItem::subMenu('Role: '.$user->getRole()),
            MenuItem::linkToCrud('My Account', 'fa fa-id-card', CsUser::class)->setAction('detail')->setEntityId($user->getId()),
        ];
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            $menuItems[] = MenuItem::linkToCrud('User Administration', 'fa fa-users', CsUser::class)->setAction('index');
        }
        $menu->addMenuItems($menuItems);

        return $menu;
    }
}
