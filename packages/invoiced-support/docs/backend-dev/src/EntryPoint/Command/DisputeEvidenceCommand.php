<?php

namespace App\EntryPoint\Command;

use App\Core\Billing\Disputes\DisputeEvidenceGenerator;
use App\Core\Billing\Disputes\StripeDisputeHandler;
use App\Core\Billing\Models\BillingProfile;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DisputeEvidenceCommand extends Command
{
    public function __construct(
        private StripeDisputeHandler $stripeDisputeHandler,
        private DisputeEvidenceGenerator $disputeEvidenceGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('billing:dispute-evidence')
            ->setDescription('Builds dispute evidence for a company')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'Billing profile ID to lookup'
            )
            ->addOption(
                'stripe-dispute',
                null,
                InputOption::VALUE_OPTIONAL,
                'Stripe dispute ID to respond to'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');
        $stripeDisputeId = $input->getOption('stripe-dispute');

        $billingProfile = $this->lookupBillingProfile($id);
        if (!$billingProfile) {
            $output->writeln("Billing Profile # $id not found");

            return 1;
        }

        if ($stripeDisputeId) {
            $dispute = $this->stripeDisputeHandler->getStripeDispute($stripeDisputeId);
            $this->stripeDisputeHandler->updateStripeDispute($dispute, $billingProfile);
            $output->writeln("Sent evidence to Stripe for dispute # $stripeDisputeId");
        } else {
            $url = $this->disputeEvidenceGenerator->generateToUrl($billingProfile);
            $output->writeln('URL: '.$url);
        }

        return 0;
    }

    /**
     * Looks up a billing profile by ID.
     */
    private function lookupBillingProfile(string|int $id): ?BillingProfile
    {
        if (is_numeric($id) && $billingProfile = BillingProfile::find($id)) {
            return $billingProfile;
        }

        return null;
    }
}
