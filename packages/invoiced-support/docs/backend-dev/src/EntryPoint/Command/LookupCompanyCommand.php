<?php

namespace App\EntryPoint\Command;

use App\Companies\Libs\CompanyCustomerServiceProfile;
use App\Companies\Models\Company;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LookupCompanyCommand extends Command
{
    public function __construct(private CompanyCustomerServiceProfile $companyProfile)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('company:lookup')
            ->setDescription('Looks up a company in the database')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'Company ID or username to lookup'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');

        $company = $this->lookupCompany($id);
        if (!$company) {
            $output->writeln("Company # $id not found");

            return 1;
        }

        $output->writeln((string) json_encode($this->companyProfile->build($company, null), JSON_PRETTY_PRINT));

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
