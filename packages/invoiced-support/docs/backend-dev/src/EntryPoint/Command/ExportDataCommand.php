<?php

namespace App\EntryPoint\Command;

use App\Companies\Models\Company;
use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\MemberACLExportJob;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportDataCommand extends Command
{
    public function __construct(private readonly Queue $queue)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('company:export')
            ->setDescription('Builds an export for a company')
            ->addArgument(
                'company',
                InputArgument::REQUIRED,
                'Company ID to export'
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

        MemberACLExportJob::create($this->queue, 'company', null, [
            'tenant_id' => (string) $id,
            'prefix' => 'company_export:',
            'ttl' => 172800, // 2 days
        ]);

        $output->writeln("Data exported for company # $id enqueued ");

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
