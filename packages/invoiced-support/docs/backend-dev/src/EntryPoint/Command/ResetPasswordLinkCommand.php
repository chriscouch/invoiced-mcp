<?php

namespace App\EntryPoint\Command;

use App\Core\Authentication\Libs\ResetPassword;
use App\Core\Authentication\Models\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetPasswordLinkCommand extends Command
{
    public function __construct(private ResetPassword $resetPassword)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('user:reset-password-link')
            ->setDescription('Generates a forgot password link for a user (does not send it)')
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'User\'s email address'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');

        $user = User::where('email', $email)->oneOrNull();
        if (!$user) {
            $output->writeln("User not found for $email");

            return 1;
        }

        $link = $this->resetPassword->buildLink((int) $user->id(), 'N/A', 'Infuse/Console');

        $output->writeln("Reset password link for $email:");
        $output->writeln((string) $link->url());
        $output->writeln('This link is valid for 4 hours');

        return 0;
    }
}
