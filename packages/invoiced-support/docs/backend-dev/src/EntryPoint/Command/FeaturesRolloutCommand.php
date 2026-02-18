<?php

namespace App\EntryPoint\Command;

use App\Companies\Models\Company;
use App\Core\Entitlements\FeatureManagement;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FeaturesRolloutCommand extends Command
{
    public function __construct(private FeatureManagement $featureManagement, string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('features:rollout')
            ->setDescription('Rolls out a feature flag across the entire user population.')
            ->addArgument('feature', InputArgument::REQUIRED, 'Feature flag name')
            ->addArgument('percent', InputArgument::REQUIRED, 'Set percent between 0 - 100')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $feature */
        $feature = $input->getArgument('feature');
        /** @var string $percentInput */
        $percentInput = $input->getArgument('percent');
        $percent = (int) $percentInput;

        // Check if feature is protected
        if ($this->featureManagement->isProtected($feature)) {
            $io->error('This feature flag is protected');

            return 1;
        }

        // Validate %
        if ($percent < 0 || $percent > 100) {
            $io->error('Invalid percent. Must be between 0 - 100');

            return 1;
        }

        $numCompanies = Company::count();
        $shouldBe = (int) round($percent * $numCompanies / 100);
        $numEnabled = $this->featureManagement->getFeatureUsage($feature);
        $io->note('Currently enabled for '.$numEnabled.' users');

        if ($numEnabled > $shouldBe) {
            $n = $numEnabled - $shouldBe;
            $io->note("Disabling the $feature feature flag for $n users.");
            $this->featureManagement->disableFeature($feature, $n);
        } elseif ($numEnabled < $shouldBe) {
            $n = $shouldBe - $numEnabled;
            $io->note("Enabling the $feature feature flag for $n users.");
            $this->featureManagement->enableFeature($feature, $n);
        }

        $io->success("Rolled out the $feature feature flag to $percent% ($shouldBe) of users.");

        return 0;
    }
}
