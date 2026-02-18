<?php

namespace App\EntryPoint\Command;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EncryptValue extends Command
{
    public function __construct(private Key $encryptionKey)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('encrypt-value')
            ->setDescription('Encrypts a value for use by the app')
            ->addArgument(
                'value',
                InputArgument::REQUIRED,
                'Value to encrypt'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $value = $input->getArgument('value');
        $output->writeln(Crypto::encrypt($value, $this->encryptionKey));

        return 0;
    }
}
