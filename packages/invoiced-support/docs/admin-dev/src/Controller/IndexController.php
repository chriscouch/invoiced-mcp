<?php

namespace App\Controller;

use App\Controller\Admin\CompanyCrudController;
use App\Controller\Admin\OrderCrudController;
use App\Controller\Admin\UserCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AbstractController
{
    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(AdminUrlGenerator $adminUrlGenerator)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_home');
    }

    #[Route(path: '/companies/{id}', name: 'shortcut_company', methods: ['GET'])]
    public function companyShortcut(string $id): Response
    {
        return $this->redirectToObject(CompanyCrudController::class, $id);
    }

    #[Route(path: '/orders/{id}', name: 'shortcut_order', methods: ['GET'])]
    public function orderShortcut(string $id): Response
    {
        return $this->redirectToObject(OrderCrudController::class, $id);
    }

    #[Route(path: '/users/{id}', name: 'shortcut_user', methods: ['GET'])]
    public function userShortcut(string $id): Response
    {
        return $this->redirectToObject(UserCrudController::class, $id);
    }

    private function redirectToObject(string $controller, string $id): Response
    {
        return $this->redirect(
            $this->adminUrlGenerator->setController($controller)
                ->setAction('detail')
                ->setEntityId($id)
                ->generateUrl()
        );
    }
}
