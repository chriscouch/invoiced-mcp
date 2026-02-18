<?php

namespace App\EntryPoint\Command;

use App\CashApplication\Models\Payment;
use App\Core\Multitenant\TenantContext;
use App\PaymentProcessing\Libs\ConvenienceFeeHelper;
use App\PaymentProcessing\Models\PaymentMethod;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixMismatchingAdyenPaymentsCommand extends Command
{
    public function __construct(
        private readonly Connection $database,
        private readonly TenantContext $tenant,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('fix:missmatching_adyen_payments')
            ->setDescription('Fixes Adyen Payments that were restored incorrectly.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // clean up duplicated convenience fee applications
        $this->database->executeQuery('DELETE FROM PaymentFlowApplications where id IN (SELECT id FROM 
          (select count(*) cnt, max(id) id from PaymentFlowApplications  where type = 2 group BY payment_flow_id HAVING cnt > 1) a
        )');

        $result = $this->database->executeQuery('select ch.tenant_id,
       ch.payment_id,
       ch.gateway_id,
       ch.amount            as charge_amount,
       t.amount             as transaction_amount,
       ch.amount - t.amount as delta,
       t.created_at         as txn_created_at,
       ch.created_at        as ch_created_at
from Charges ch
         join MerchantAccountTransactions t on t.id = ch.merchant_account_transaction_id
where ch.amount <> t.amount and t.type = 1 and (ch.currency = t.currency or ch.currency is null)
order by ch.created_at'
        )->iterateAssociative();

        foreach ($result as $row) {
            $output->writeln('Processing payment: '.$row['payment_id'].' - '.$row['delta']);

            $payment = Payment::queryWithoutMultitenancyUnsafe()
                ->where('id', $row['payment_id'])
                ->oneOrNull();

            if (!$payment) {
                $output->writeln('Payment not found: '.$row['payment_id']);

                continue;
            }

            $tenant = $payment->tenant();
            $this->tenant->set($tenant);

            $transactions = $payment->getTransactions();

            $customer = $payment->customer();
            if (!$customer) {
                continue;
            }

            $charge = $payment->charge;

            foreach ($transactions as $transaction) {
                if ($transaction->isConvenienceFee()) {
                    if ($charge) {
                        $chargeAmount = $charge->getAmount();
                        $fee = ConvenienceFeeHelper::calculate(PaymentMethod::instance($tenant, $payment->method), $customer, $chargeAmount);

                        if ($fee['amount'] && $fee['amount']->toDecimal() === 1 * $row['delta']) {
                            $appliedTo = array_filter($payment->applied_to, fn ($item) => 'convenience_fee' !== $item['type']);
                            $this->saveCharge($output, $row, $payment, $appliedTo);

                            continue 2;
                        }

                        // charges that haven't been restored initially to be restored now
                        if ($fee['total'] && $payment->getAmount()->equals($fee['total'])) {
                            $charge->amount = $payment->amount;
                            $charge->saveOrFail();

                            continue 2;
                        }

                        if ($fee['amount'] && $row['delta'] < 0) {
                            $appliedTo = array_map(function ($item) use ($row) {
                                if ('convenience_fee' !== $item['type']) {
                                    $item['amount'] = -1 * $row['delta'];
                                }

                                return $item;
                            }, $payment->applied_to);
                            $this->saveCharge($output, $row, $payment, $appliedTo);

                            continue 2;
                        }

                        $output->writeln('Payment has convenience fee transaction: '.$row['payment_id']);
                    }

                    continue 2;
                }
            }

            $fee = ConvenienceFeeHelper::calculate(PaymentMethod::instance($tenant, $payment->method), $customer, $payment->getAmount());
            if (!$fee['amount']) {
                $output->writeln('No fee for payment: '.$row['payment_id']);

                $appliedTo = $payment->applied_to;

                if (1 === count($appliedTo)) {
                    $appliedTo[0]['amount'] = $row['transaction_amount'];
                    $this->saveCharge($output, $row, $payment, $appliedTo);
                }
                continue;
            }

            $amount = $fee['amount']->toDecimal();
            if ($amount !== -1 * $row['delta']) {
                $output->writeln('Payment amount missmatch: '.$row['payment_id'].' - '.$amount.' - '.$row['delta']);

                continue;
            }

            $appliedTo = $payment->applied_to;
            $appliedTo[] = [
                'type' => 'convenience_fee',
                'amount' => $amount,
            ];
            $this->saveCharge($output, $row, $payment, $appliedTo);
        }

        $this->tenant->clear();

        return 0;
    }

    private function saveCharge(OutputInterface $output, array $row, Payment $payment, array $appliedTo): void
    {
        $payment->amount = $row['transaction_amount'];
        $payment->applied_to = $appliedTo;
        if (!$payment->save()) {
            $output->writeln('Failed to save payment: '.$row['payment_id'].json_encode($payment->getErrors()));

            return;
        }

        if ($charge = $payment->charge) {
            $charge->amount = $payment->amount;
            if (!$charge->save()) {
                $output->writeln('Failed to save charge: '.$row['payment_id'].json_encode($charge->getErrors()));
            }
        }
    }
}
