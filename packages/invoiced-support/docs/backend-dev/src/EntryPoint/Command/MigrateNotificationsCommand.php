<?php

namespace App\EntryPoint\Command;

use App\Companies\Models\Company;
use App\Notifications\Libs\MigrateV2Notifications;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateNotificationsCommand extends Command
{
    public function __construct(private MigrateV2Notifications $migrate)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('migrate-v2-notifications')
            ->setDescription('Migrates a list of companies to V2 notifications')
            ->addArgument(
                'companies',
                InputArgument::REQUIRED,
                'List of company IDs to migrate'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ids = $input->getArgument('companies');
        if (!is_string($ids)) {
            return 1;
        }

        foreach (explode(',', $ids) as $id) {
            $output->writeln("Migrating company # $id");
            $company = Company::findOrFail($id);
            $this->migrate->migrate($company);
            $output->writeln("✔️ Migrated company # $id");
        }

        return 0;
    }
}
