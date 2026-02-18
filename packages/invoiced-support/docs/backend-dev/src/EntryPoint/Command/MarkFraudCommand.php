<?php

namespace App\EntryPoint\Command;

use App\Companies\Libs\MarkCompanyFraudulent;
use App\Companies\Models\Company;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MarkFraudCommand extends Command implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(private MarkCompanyFraudulent $markCompanyFraudulent)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('company:mark-fraud')
            ->setDescription('Marks the company as fraud and cancels it')
            ->addArgument(
                'company',
                InputArgument::REQUIRED,
                'Company ID to cancel'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('company');

        $company = $this->lookupCompany($id);
        if (!$company) {
            $output->writeln("Company # $id not found");

            return 1;
        }

        $this->markCompanyFraudulent->markFraud($company, $output);

        return 0;
    }

    /**
     * Looks up a company by ID, username, or email.
     */
    private function lookupCompany(string $id): ?Company
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
