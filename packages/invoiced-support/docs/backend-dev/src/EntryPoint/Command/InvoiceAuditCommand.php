<?php

namespace App\EntryPoint\Command;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\TenantContext;
use App\ActivityLog\Libs\EventSpool;
use App\PaymentPlans\Models\PaymentPlan;
use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InvoiceAuditCommand extends Command
{
    public function __construct(private Connection $database, private TenantContext $tenant)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('audit:invoices')
            ->setDescription('Finds and corrects invalid invoices')
            ->addArgument(
                'company',
                InputArgument::OPTIONAL,
                'Company ID constraint'
            )
            ->addOption(
                'invoiceIds',
                null,
                InputOption::VALUE_REQUIRED,
                'List of invoice IDs to audit'
            )
            ->addOption(
                'fix',
                null,
                InputOption::VALUE_NONE,
                'Writes any suggested changes'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cId = $input->getArgument('company');
        $invoiceIds = $input->getOption('invoiceIds');
        $fix = $input->getOption('fix');

        EventSpool::disable();

        if ($cId) {
            $company = Company::findOrFail($cId);
            if ($invoiceIds) {
                $invoiceIds = explode(',', $invoiceIds);
            } else {
                $invoiceIds = null;
            }
            $this->runFor($company, $output, $invoiceIds, $fix);
        } else {
            if ($invoiceIds) {
                throw new Exception('Need to specify company ID with invoice IDs');
            }

            $companies = Company::all();
            foreach ($companies as $company) {
                $this->runFor($company, $output, null, $fix);
            }
        }

        return 0;
    }

    private function runFor(Company $company, OutputInterface $output, ?array $invoiceIds, bool $fix): void
    {
        $output->writeln("Running audit for company # {$company->id()}...");

        $this->tenant->set($company);

        $this->correctBalance($company, $output, $invoiceIds, $fix);
    }

    private function correctBalance(Company $company, OutputInterface $output, ?array $invoiceIds, bool $fix): void
    {
        $parameters = [$company->id()];
        if (is_array($invoiceIds)) {
            $query = 'SELECT * FROM Invoices WHERE tenant_id=? AND id IN ('.str_repeat('?', count($invoiceIds)).')';
            $parameters = array_merge($parameters, $invoiceIds);
        } else {
            $query = 'SELECT * FROM Invoices WHERE tenant_id=?';
        }

        $countQuery = str_replace('*', 'COUNT(*)', $query);
        $count = $this->database->fetchOne($countQuery, $parameters);
        $n = 0;
        $perPage = 10000;
        $numPages = ceil($count / $perPage);

        for ($i = 0; $i < $numPages; ++$i) {
            $pageQuery = $query.' LIMIT '.($i * $perPage).', '.$perPage;
            $invoices = $this->database->fetchAllAssociative($pageQuery, $parameters);

            foreach ($invoices as $invoice) {
                ++$n;

                $total = Money::fromDecimal($invoice['currency'], $invoice['total']);

                // calculate amount paid
                $query = 'SELECT type,sum(amount) as amount FROM Transactions WHERE `status`="succeeded" AND invoice=? GROUP BY `type`';
                $transactions = $this->database->fetchAllAssociative($query, [$invoice['id']]);

                $amountPaid = new Money($invoice['currency'], 0);
                foreach ($transactions as $row) {
                    if ('charge' == $row['type'] || 'payment' == $row['type']) {
                        $amountPaid = $amountPaid->add(Money::fromDecimal($invoice['currency'], $row['amount']));
                    } elseif ('refund' == $row['type']) {
                        $amountPaid = $amountPaid->subtract(Money::fromDecimal($invoice['currency'], $row['amount']));
                    }
                }

                $errors = [];

                if ($amountPaid->isNegative()) {
                    $errors[] = "amount paid was less than 0: $amountPaid";
                }

                if ($amountPaid->greaterThan($total)) {
                    $errors[] = "amount paid was greater than total: $amountPaid > $total";
                }

                $amountPaid = $amountPaid->max(new Money($invoice['currency'], 0))->min($total);

                // calculate amount credited
                $amountCredited = (float) $this->database->fetchOne('SELECT -sum(amount) FROM Transactions WHERE type="adjustment" and invoice=?', [$invoice['id']]);
                $amountCredited = Money::fromDecimal($invoice['currency'], $amountCredited);

                if ($amountCredited->isNegative()) {
                    $errors[] = "amount credited was less than 0: $amountCredited";
                }

                if ($amountCredited->greaterThan($total)) {
                    $errors[] = "amount credited was greater than total: $amountCredited > $total";
                }

                $amountCredited = $amountCredited->max(new Money($invoice['currency'], 0))->min($total);

                // calculate balance
                $balance = $total->subtract($amountPaid)->subtract($amountCredited)->max(new Money($invoice['currency'], 0));

                // check if amount paid, amount credited, and balance are correct
                $oldAmountPaid = Money::fromDecimal($invoice['currency'], $invoice['amount_paid']);
                $oldAmountCredited = Money::fromDecimal($invoice['currency'], $invoice['amount_credited']);
                $oldBalance = Money::fromDecimal($invoice['currency'], $invoice['balance']);

                if (!$amountPaid->equals($oldAmountPaid)) {
                    $errors[] = "amount paid was wrong: $oldAmountPaid should be $amountPaid";
                }

                if (!$amountCredited->equals($oldAmountCredited)) {
                    $errors[] = "amount credited was wrong: $oldAmountCredited should be $amountCredited";
                }

                if (!$balance->equals($oldBalance)) {
                    $errors[] = "balance was wrong: $oldBalance should be $balance";
                }

                if (count($errors) > 0) {
                    $output->writeln("- Invoice # {$invoice['id']}: ".implode(', ', $errors));

                    if ($fix) {
                        $params = [
                            'amount_paid' => $amountPaid->toDecimal(),
                            'amount_credited' => $amountCredited->toDecimal(),
                            'balance' => $balance->toDecimal(),
                        ];

                        // update status as paid
                        if ($balance->isZero()) {
                            $params['status'] = InvoiceStatus::Paid->value;
                            $params['closed'] = true;
                            $params['paid'] = true;
                            $params['date_paid'] = $this->database->fetchOne('SELECT max(`date`) FROM Transactions WHERE `status`="succeeded" AND invoice=?', [$invoice['id']]);
                        }

                        $this->updateInvoice($invoice['id'], $params);
                    }
                }

                // check the payment plan
                if ($invoice['payment_plan_id']) {
                    $this->correctPaymentPlan($output, $fix, $amountPaid, $amountCredited, $invoice, $total, $balance);
                }
            }

            $remaining = $count - $n;
            if ($n > 0 && $remaining > 0) {
                $percent = round((1 - $remaining / $count) * 100);
                $output->writeln("$remaining remaining ($percent% done)");
            }
        }

        $output->writeln("Analyzed $count invoices");
    }

    private function correctPaymentPlan(OutputInterface $output, bool $fix, Money $amountPaid, Money $amountCredited, array $invoice, Money $total, Money $balance): void
    {
        $remaining = $amountPaid->add($amountCredited);
        $installments = $this->database->fetchAllAssociative('SELECT id,amount,balance FROM PaymentPlanInstallments WHERE payment_plan_id=? ORDER BY date ASC,id ASC', [$invoice['payment_plan_id']]);

        // determine plan total
        $zero = new Money($invoice['currency'], 0);
        $planTotal = $zero;
        foreach ($installments as &$i) {
            $i['amount'] = Money::fromDecimal($invoice['currency'], $i['amount']);
            $planTotal = $planTotal->add($i['amount']);
        }

        // reduce amount remaining by the delta between the invoice total and plan total
        // to account for payment plans less than the invoice total
        $remaining = $remaining->subtract($total)->add($planTotal);

        // recalculate each installment
        foreach ($installments as $installment) {
            $apply = $remaining->min($installment['amount']);
            $installmentBalance = $installment['amount']->subtract($apply);

            $oldInstallmentBalance = Money::fromDecimal($invoice['currency'], $installment['balance']);
            if (!$installmentBalance->equals($oldInstallmentBalance)) {
                $output->writeln("- Installment # {$installment['id']} balance was wrong: $oldInstallmentBalance should be $installmentBalance");

                if ($fix) {
                    $params = ['balance' => $installmentBalance->toDecimal()];
                    $this->updateInstallment($installment['id'], $params);
                }
            }

            $remaining = $remaining->subtract($apply);
        }

        // determine payment plan status
        $oldStatus = $this->database->fetchOne('SELECT `status` FROM PaymentPlans WHERE id=?', [$invoice['payment_plan_id']]);
        if ($balance->isZero()) {
            $status = PaymentPlan::STATUS_FINISHED;
        } elseif (PaymentPlan::STATUS_FINISHED == $oldStatus) {
            $status = PaymentPlan::STATUS_ACTIVE;
        } else {
            $status = $oldStatus;
        }

        if ($status != $oldStatus) {
            $output->writeln("Payment plan # {$invoice['payment_plan_id']} status is wrong: '$oldStatus' should be '$status'");
            if ($fix) {
                $params = ['status' => $status];
                $this->updatePaymentPlan($invoice['payment_plan_id'], $params);
            }
        }
    }

    private function updateInvoice(int $id, array $values): void
    {
        $this->database->update('Invoices', $values, ['id' => $id]);
    }

    private function updatePaymentPlan(int $id, array $values): void
    {
        $this->database->update('PaymentPlans', $values, ['id' => $id]);
    }

    private function updateInstallment(int $id, array $values): void
    {
        $this->database->update('PaymentPlanInstallments', $values, ['id' => $id]);
    }
}
