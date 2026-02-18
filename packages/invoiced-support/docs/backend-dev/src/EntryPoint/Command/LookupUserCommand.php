<?php

namespace App\EntryPoint\Command;

use App\Companies\Libs\UserCustomerServiceProfile;
use App\Core\Authentication\Models\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LookupUserCommand extends Command
{
    public function __construct(private UserCustomerServiceProfile $userProfile)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('user:lookup')
            ->setDescription('Looks up a user in the database')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'User ID or email address to look up'
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

        $output->writeln((string) json_encode($this->userProfile->build($user), JSON_PRETTY_PRINT));

        return 0;
    }
}
