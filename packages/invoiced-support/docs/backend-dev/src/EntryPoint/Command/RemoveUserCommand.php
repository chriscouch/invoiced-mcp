<?php

namespace App\EntryPoint\Command;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Authentication\Models\User;
use App\Core\Multitenant\TenantContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveUserCommand extends Command
{
    public function __construct(private TenantContext $tenant)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('company:remove-user')
            ->setDescription('Removes a user from a company')
            ->addArgument(
                'company',
                InputArgument::REQUIRED,
                'Company ID to add remove user from'
            )
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'Email address of the user'
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

        $user = User::where('email', $email)->oneOrNull();
        if (!$user) {
            $output->writeln("User not found for $email");

            return 1;
        }

        $member = Member::getForUser($user);
        if (!$member) {
            $output->writeln("$email was already not a member of company # $id");

            return 0;
        }

        if (!$member->delete()) {
            $output->writeln("Could not remove $email from company # $id");

            return 1;
        }

        $output->writeln("$email removed from company # $id");

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
