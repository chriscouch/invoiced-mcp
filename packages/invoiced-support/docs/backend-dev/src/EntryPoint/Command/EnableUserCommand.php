<?php

namespace App\EntryPoint\Command;

use App\Core\Authentication\Models\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnableUserCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('user:enable')
            ->setDescription('Enables a user account')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'User ID or email address to enable'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');

        if (is_numeric($id)) {
            $user = User::find($id);
        } else {
            $user = User::where('email', $id)->oneOrNull();
        }

        if (!$user) {
            $output->writeln("Could not find a user matching $id");

            return 1;
        }

        if ($user->enabled) {
            $output->writeln("User # {$user->id()} is already enabled");

            return 0;
        }

        // enable the user account
        $user->enabled = true;
        if (!$user->save()) {
            $output->writeln('Could not enable user');

            return 1;
        }

        $output->writeln('Enabled user account # '.$user->id());

        return 0;
    }
}
