<?php

namespace App\EntryPoint\Command;

use App\Core\Authentication\OAuth\Models\OAuthApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class OauthNewAppCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('oauth:new-app')
            ->setDescription('Creates a new OAuth application')
            ->addArgument('name', InputArgument::REQUIRED, 'Application name')
            ->addOption('redirect-uri', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'List of allowed redirect URIs', [])
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = (string) $input->getArgument('name');
        $redirectUris = (array) $input->getOption('redirect-uri');
        $application = OAuthApplication::makeNewApp($name, $redirectUris);

        $io->success('Application created');
        $io->definitionList(
            ['Name' => $application->name],
            ['Client ID' => $application->identifier],
            ['Client Secret' => $application->secret],
            ['Redirect URIs' => implode(', ', $application->redirect_uris)],
        );

        return 0;
    }
}
