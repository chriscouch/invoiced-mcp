<?php

namespace App\EntryPoint\Command;

use App\Core\Multitenant\TenantContext;
use App\Integrations\Adyen\Exception\AdyenReconciliationException;
use App\Integrations\Adyen\Operations\SaveAdyenPayment;
use App\PaymentProcessing\Models\PaymentFlow;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixMissingAdyenPaymentsCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly Connection $database,
        private readonly TenantContext $tenant,
        private readonly SaveAdyenPayment $saveAdyenPayment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('fix:missing_adyen_payments')
            ->setDescription('Fixes missing Adyen Payments.')
            ->addArgument(
                'start_date',
                InputArgument::OPTIONAL,
                'Date to start query',
                CarbonImmutable::now()->startOfDay()->toIso8601String()
            )
            ->addArgument(
                'end_date',
                InputArgument::OPTIONAL,
                'Date to end query',
                CarbonImmutable::now()->endOfDay()->toIso8601String()
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $date1 = $input->getArgument('start_date');
        $date2 = $input->getArgument('end_date');

        $result = $this->database->executeQuery('select a.reference, a.result, a.id from 
            (select REPLACE(REGEXP_SUBSTR(result, "pspReference\":\"([A-Z0-9]+)"), "pspReference\":\"", "") as id, result, reference  from AdyenPaymentResults
            where REGEXP_SUBSTR(result, "Refused") = ""
                and REGEXP_SUBSTR(result, "value\":0") = ""
                and REGEXP_SUBSTR(result, "CANCELLED") = ""
                and created_at BETWEEN ? AND ?
            ) a
            LEFT JOIN (Select gateway_id,id from Charges where gateway = \'flywire_payments\') c
            ON a. id = c.gateway_id
            where c.gateway_id is null
            group by a. id
            ORDER BY c.id DESC', [
                $date1,
                $date2,
            ])->iterateAssociative();

        foreach ($result as $row) {
            $data = json_decode($row['result'], true);

            if (!($data['amount']['value'] ?? null)) {
                continue;
            }

            /** @var ?PaymentFlow $flow */
            $flow = PaymentFlow::queryWithoutMultitenancyUnsafe()->where('identifier', $row['reference'])
                ->oneOrNull();
            if (!$tenant = $flow?->tenant()) {
                continue;
            }

            $this->tenant->set($tenant);

            $this->logger->info("Payment reconciliation dispatched: {$row['reference']}");
            try {
                $this->saveAdyenPayment->tryReconcile($row['id'], $row['reference']);
            } catch (AdyenReconciliationException) {
                // do nothing
            }
        }

        $this->tenant->clear();

        return 0;
    }
}
