<?php

namespace App\Controller\Admin;

use App\Entity\CustomerAdmin\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CsUserCrudController extends AbstractCrudController
{
    private const ROLES = [
        'Administrator' => 'administrator',
        'Customer Support' => 'cs',
        'Marketing' => 'marketing',
        'Sales' => 'sales',
    ];

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        if ($this->isGranted('new', User::class)) {
            $new = Action::new('newUser', 'New User')
                ->createAsGlobalAction()
                ->addCssClass('btn btn-primary')
                ->linkToRoute('register');
            $actions->add('index', $new);
        }

        return $actions->add('index', 'detail')
            ->disable('new')
            ->setPermission('edit', 'edit')
            ->setPermission('delete', 'delete')
            ->setPermission('index', 'list');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'User Administration')
            ->setSearchFields(['id', 'first_name', 'last_name', 'email', 'role', 'time_zone'])
            ->setDefaultSort(['id' => 'ASC'])
            ->overrideTemplate('crud/detail', 'customizations/show/cs_user_show.html.twig')
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        $firstName = TextField::new('first_name');
        $lastName = TextField::new('last_name');
        $email = TextField::new('email');
        $password = TextField::new('password');
        $role = ChoiceField::new('role');
        $role->setChoices(self::ROLES);
        $timeZone = TextField::new('time_zone', 'Time Zone');
        $panel1 = FormField::addPanel('Settings');
        $id = IntegerField::new('id', 'ID');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $firstName, $lastName, $email, $role];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$firstName, $lastName, $email, $role, $timeZone];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$firstName, $lastName, $email, $password, $role, $timeZone];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$panel1, $firstName, $lastName, $email, $role, $timeZone];
        }

        return [];
    }
}
