<?php

namespace App\Controller;

use App\Controller\Admin\AccountsPayableSettingsCrudController;
use App\Controller\Admin\AccountsReceivableSettingsCrudController;
use App\Controller\Admin\CashApplicationSettingsCrudController;
use App\Controller\Admin\CsAuditEntryCrudController;
use App\Controller\Admin\CsUserCrudController;
use App\Controller\Admin\CustomerPortalSettingsCrudController;
use App\Controller\Admin\MemberCrudController;
use App\Controller\Admin\SubscriptionBillingSettingsCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EntityListController extends AbstractController
{
    private const EXCLUDED_ENTITIES = [
        CsAuditEntryCrudController::class,
        CsUserCrudController::class,
        MemberCrudController::class,
        AccountsPayableSettingsCrudController::class,
        AccountsReceivableSettingsCrudController::class,
        CashApplicationSettingsCrudController::class,
        CustomerPortalSettingsCrudController::class,
        SubscriptionBillingSettingsCrudController::class,
    ];

    /** @var CrudControllerInterface[] */
    private iterable $controllers;

    public function __construct(iterable $controllers)
    {
        $this->controllers = $controllers;
    }

    #[Route(path: '/admin/entities', name: 'entity_list')]
    public function index(AdminUrlGenerator $adminUrlGenerator): Response
    {
        $entities = [];
        foreach ($this->controllers as $controller) {
            $className = get_class($controller);
            if (in_array($className, self::EXCLUDED_ENTITIES)) {
                continue;
            }

            $crud = Crud::new();
            $controller->configureCrud($crud);
            $name = (string) $crud->getAsDto()->getCustomPageTitle('index'); /* @phpstan-ignore-line */
            if (!$name) {
                $name = (string) $crud->getAsDto()->getEntityLabelInPlural(); /* @phpstan-ignore-line */
            }
            $initials = '';
            foreach (explode(' ', $name) as $w) {
                $initials .= $w[0];
            }

            $entities[] = [
                'id' => $className,
                'name' => $name,
                'initials' => $initials,
                'url' => $adminUrlGenerator
                    ->setController($className)
                    ->setAction('index')
                    ->generateUrl(),
            ];
        }
        usort($entities, fn (array $a, array $b) => $a['name'] <=> $b['name']);

        return $this->render('entity_list/index.html.twig', [
            'entities' => $entities,
        ]);
    }
}
