<?php

namespace App\EntryPoint\Command;

use App\Core\Billing\Action\BillOverageAction;
use App\Core\Billing\Models\OverageCharge;
use App\Core\Billing\Usage\OverageChargeGenerator;
use App\Core\Billing\ValueObjects\MonthBillingPeriod;
use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class OveragesCommand extends Command
{
    public function __construct(
        private OverageChargeGenerator $usageCalculator,
        private BillOverageAction $billOverageAction
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('billing:overages')
            ->setDescription('Lists usage overage charges for a given month')
            ->addArgument(
                'month',
                InputArgument::OPTIONAL,
                'Month in form 201510 for Oct 2015'
            )
            ->addOption(
                'detail',
                null,
                InputOption::VALUE_NONE,
                'Show per-company breakout of overage charges'
            )
            ->addOption(
                'bill',
                null,
                InputOption::VALUE_NONE,
                'Should any unbilled usage be billed?'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $month = $input->getArgument('month');
        $bill = $input->getOption('bill');
        $showDetail = $input->getOption('detail');

        if (!$month) {
            $month = date('Ym');
        }

        // validate the month input
        if ($month > date('Ym')) {
            $output->writeln("Cannot get usage for $month because it's in the future.");

            return 1;
        } elseif ($month < 201508) {
            $output->writeln("Cannot get usage for $month because it's before we started tracking usage.");

            return 1;
        } elseif ($bill && $month == date('Ym')) {
            $output->writeln("Cannot bill usage for $month because the month has not ended yet.");

            return 1;
        }

        // build overage charges table
        $charges = iterator_to_array($this->usageCalculator->generateAllOverages(new MonthBillingPeriod($month)));

        // bill out overage charges when requested
        if ($bill) {
            $output->writeln('Billing for usage overage charges');

            $n = 0;
            foreach ($charges as $charge) {
                if ($this->billOverageAction->billCharge($charge)) {
                    ++$n;
                }
            }
            $output->writeln("-- Billed $n companies for overage charges");
        }

        // output the result
        $this->outputResult($month, $charges, $output, $showDetail);

        return 0;
    }

    /**
     * Outputs result of the command.
     *
     * @param OverageCharge[] $charges
     */
    private function outputResult(string $month, array $charges, OutputInterface $output, bool $showDetail): void
    {
        if (0 === count($charges)) {
            $output->writeln("No overage charges to report for $month");

            return;
        }

        // format results
        $total = new Money('usd', 0);

        $rows = [];
        foreach ($charges as $charge) {
            $chargeTotal = Money::fromDecimal('usd', $charge->total);
            $total = $total->add($chargeTotal);

            $rows[] = [
                $charge->tenant()->name,
                $charge->quantity,
                $charge->dimension,
                MoneyFormatter::get()->format($chargeTotal),
                $charge->billed ? 'Yes' : 'No',
                $charge->billing_system,
            ];
        }

        $output->writeln(count($charges)." overage charges for $month totaling ".MoneyFormatter::get()->format($total).':');

        // output a table
        if ($showDetail) {
            $table = new Table($output);
            $table->setHeaders([
                    'Company',
                    'Qty',
                    'Dimension',
                    'Total',
                    'Billed',
                    'Billing System',
                ])
                ->setRows($rows)
                ->render();
        }
    }
}
