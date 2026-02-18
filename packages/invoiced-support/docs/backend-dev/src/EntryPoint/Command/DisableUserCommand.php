<?php

namespace App\EntryPoint\Command;

use App\Core\Authentication\Libs\LoginHelper;
use App\Core\Authentication\Models\User;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DisableUserCommand extends Command
{
    public function __construct(
        private Connection $database,
        private LoginHelper $loginHelper
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('user:disable')
            ->setDescription('Disables a user account')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'User ID or email address to disable'
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

        if (!$user->enabled) {
            $output->writeln("User # {$user->id()} is already disabled");

            return 0;
        }

        // disable the user account
        $user->enabled = false;
        if (!$user->save()) {
            $output->writeln('Could not disable user');

            return 1;
        }

        $output->writeln('Disabled user account # '.$user->id());

        // sign them out of all sessions
        $this->loginHelper->signOutAllSessions($user);
        $output->writeln('Signed user out of all active sessions');

        // delete all active API keys
        $this->database->delete('ApiKeys', ['user_id' => $user->id()]);
        $output->writeln('Deleted all active API keys');

        return 0;
    }
}
