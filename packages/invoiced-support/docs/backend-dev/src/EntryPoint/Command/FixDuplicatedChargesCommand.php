<?php

namespace App\EntryPoint\Command;

use App\CashApplication\Models\Transaction;
use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\PaymentProcessing\Models\Charge;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FixDuplicatedChargesCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly TenantContext         $tenant,
        private readonly Connection            $database,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('fix:duplicated-charges')
            ->setDescription('Fixes duplicated charges.')
            ->addOption(
                'dry-run',
                '-d',
                InputOption::VALUE_NONE,
                'Do not execute the payment',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = $input->getOption('dry-run');
        $items = $this->database->fetchAllAssociative('select tenant_id, gateway, c1.gateway_id, count(*) cnt from Charges c1 group by tenant_id, gateway, gateway_id having cnt > 1');

        foreach ($items as $item) {
            $company = Company::where('id', $item['tenant_id'])->one();

            // IMPORTANT: set the current tenant to enable multitenant operations
            $this->tenant->set($company);

            /** @var Charge[] $charges */
            $charges = Charge::where('gateway', $item['gateway'])
                ->where('gateway_id', $item['gateway_id'])
                ->execute();

            $isVoided = false;
            foreach ($charges as $charge) {
                $output->writeln("Processiong charge {$charge->id}  and gateway_id {$charge->gateway_id}");
                if ($charge->payment?->voided) {
                    $this->deleteCharge($output, $charge, $dryRun);
                    $isVoided = true;
                }
            }


            if (!$isVoided) {
                foreach ($charges as $charge) {
                    if (!$charge->refunded) {
                        $this->deleteCharge($output, $charge, $dryRun);

                        break;
                    }
                }
            }
        }

        $this->database->executeQuery('SET SESSION max_statement_time=600;');
        $gatewaysIds = $this->database->fetchFirstColumn('select gateway_id, count(*) cnt from Charges c1 group by gateway_id having cnt > 1');
        $output->writeln('id,tenant_id,payment_id,currency,amount,customer_id,status,amount_refunded,refunded,disputed,receipt_email,failure_message,payment_source_type,payment_source_id,gateway,gateway_id,last_status_check,created_at,updated_at,merchant_account_id,payment_flow_id,description,merchant_account_transaction_id');
        foreach ($gatewaysIds as $gatewayId) {
            $charges = $this->database->fetchAllAssociative('select * from Charges c1 where gateway_id = :gateway_id', [
                'gateway_id' => $gatewayId
            ]);

            foreach ($charges as $charge) {
                $output->writeln(implode(",", $charge));
            }
        }

        return 0;
    }

    private function deleteCharge(OutputInterface $output, Charge $charge, bool $dryRun): void
    {
        $output->writeln("Voiding and deleting charge {$charge->id}  and gateway_id {$charge->gateway_id}");
        if ($dryRun) {
            return;
        }
        $transaction = $charge->payment?->getTransactions() ?? [];
        array_walk($transaction, fn(Transaction $transaction) => $transaction->delete());
        $charge->payment?->delete();
        $this->database->delete('Charges',  [
            'id' => $charge->id
        ]);
    }
}
