<?php

namespace App\EntryPoint\Command;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Authentication\Models\User;
use App\Core\Multitenant\TenantContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AddUserCommand extends Command
{
    public function __construct(private TenantContext $tenant)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('company:add-user')
            ->setDescription('Adds a user as a member of a company')
            ->addArgument(
                'company',
                InputArgument::REQUIRED,
                'Company ID or username to add user to'
            )
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'Email address of a new or existing user'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('company');
        $email = $input->getArgument('email');

        $company = $this->lookupCompany($id);
        if (!$company) {
            $output->writeln("Company # $id not found");

            return 1;
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        $existingMember = false;
        $member = new Member();
        $user = User::where('email', $email)->oneOrNull();
        if ($user) {
            Member::skipExpiredCheck();
            $existingMember = Member::getForUser($user);
            if ($existingMember && 0 == $existingMember->expires) {
                $output->writeln("$email is already a member of company # $id");

                return 0;
            }
        }

        if ($existingMember) {
            $existingMember->expires = strtotime('+1 day'); // limit access to 24 hours
            $saved = $existingMember->save();
        } else {
            $member->expires = strtotime('+1 day'); // limit access to 24 hours
            $member->email = $email; /* @phpstan-ignore-line */
            $member->role = Role::ADMINISTRATOR;
            $saved = $member->skipMemberCheck()->save();
        }

        if (!$saved) {
            $output->writeln("Could not add $email to company # $id");

            return 1;
        }

        $output->writeln("$email added to company # $id");

        return 0;
    }

    /**
     * Looks up a company by ID, username, or email.
     *
     * @param string|int $id
     */
    private function lookupCompany($id): ?Company
    {
        if (is_numeric($id) && $company = Company::find($id)) {
            return $company;
        }

        // try username
        if ($company = Company::where('username', $id)->oneOrNull()) {
            return $company;
        }

        // try email
        if ($company = Company::where('email', $id)->oneOrNull()) {
            return $company;
        }

        return null;
    }
}
