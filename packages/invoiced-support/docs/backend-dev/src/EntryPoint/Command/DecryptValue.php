<?php

namespace App\EntryPoint\Command;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DecryptValue extends Command
{
    public function __construct(private Key $encryptionKey)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('decrypt-value')
            ->setDescription('Decrypts an encrypted value')
            ->addArgument(
                'ciphertext',
                InputArgument::REQUIRED,
                'Value to decrypt'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $value = $input->getArgument('ciphertext');
        $output->writeln(Crypto::decrypt($value, $this->encryptionKey));

        return 0;
    }
}
