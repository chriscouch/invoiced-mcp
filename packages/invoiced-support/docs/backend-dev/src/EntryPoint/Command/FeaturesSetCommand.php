<?php

namespace App\EntryPoint\Command;

use App\Companies\Models\Company;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FeaturesSetCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('features:set')
            ->setDescription('Sets a feature flag for a specific company')
            ->addArgument('company', InputArgument::REQUIRED, 'Tenant ID')
            ->addArgument('feature', InputArgument::REQUIRED, 'Feature flag name')
            ->addArgument('value', InputArgument::OPTIONAL, 'Feature flag name', '1')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $companies */
        $companies = $input->getArgument('company');
        $companyIds = explode(',', $companies);
        foreach ($companyIds as $companyId) {
            $company = Company::findOrFail($companyId);
            /** @var string $feature */
            $feature = $input->getArgument('feature');
            /** @var string $value */
            $value = $input->getArgument('value');
            if ('1' === $value) {
                $company->features->enable($feature);
            } elseif ('0' === $value) {
                $company->features->disable($feature);
            } else {
                throw new InvalidArgumentException('Invalid value: ' . $value);
            }
        }

        $io->success('Feature flag set!');

        return 0;
    }
}
