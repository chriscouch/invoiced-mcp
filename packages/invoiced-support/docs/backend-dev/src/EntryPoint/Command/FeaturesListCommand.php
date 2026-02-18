<?php

namespace App\EntryPoint\Command;

use App\Companies\Models\Company;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FeaturesListCommand extends Command
{
    public function __construct(private Connection $database, string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('features:list')
            ->setDescription('Lists all feature flags and usage')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $numCompanies = Company::count();
        $rows = $this->database->fetchAllAssociative('SELECT feature,count(*) as n FROM Features WHERE enabled=1 GROUP BY feature ORDER BY feature');
        $features = [];
        foreach ($rows as $row) {
            $features[] = [
                $row['feature'],
                $row['n'],
                round($row['n'] / $numCompanies * 100).'%',
            ];
        }

        $io = new SymfonyStyle($input, $output);
        $io->table([
            'Feature',
            '# Accounts',
            '% Enabled',
        ], $features);

        return 0;
    }
}
