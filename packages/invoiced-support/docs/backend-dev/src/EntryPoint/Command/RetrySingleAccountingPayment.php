<?php

namespace App\EntryPoint\Command;

use App\CashApplication\Models\Payment;
use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Event\ModelCreated;
use App\Integrations\AccountingSync\AccountingMappingFactory;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Models\AbstractMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\NetSuite\Exceptions\NetSuiteReconciliationException;
use App\Integrations\NetSuite\Libs\NetSuiteApi;
use App\Integrations\NetSuite\Models\NetSuiteAccount;
use App\Integrations\NetSuite\Writers\NetSuiteWriterFactory;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RetrySingleAccountingPayment extends Command
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly NetSuiteApi           $netSuiteApi,
        private readonly NetSuiteWriterFactory $factory,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('retry-accounting-payment')
            ->setDescription('Retries accounting payments')
            ->addArgument(
                'tenant_id',
                InputArgument::OPTIONAL,
                'Payment id'
            );
    }

    public function getTasks(Company $tenant): iterable
    {
        return $this->connection->fetchFirstColumn(
            "SELECT p.id
                FROM Payments p
                JOIN AccountingSyncProfiles  asp ON p.tenant_id = asp.tenant_id AND (integration = 2 OR write_payments = 1)
                LEFT JOIN AccountingPaymentMappings a ON a.payment_id = p.id
                LEFT JOIN ReconciliationErrors re ON re.object = 'payment' AND re.object_id = p.id
                WHERE
                    p.created_at > :created
                    AND p.tenant_id = :tenant
                    AND a.accounting_id IS NULL
                    AND p.voided = 0
                    AND re.id is null
                ", [
            "created" => CarbonImmutable::now()->subWeek()->toDateTimeString(),
            "tenant" => $tenant->id,
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenantId = $input->getArgument('tenant_id');

        $tenant = Company::findOrFail($tenantId);
        $this->tenant->set($tenant);

        $tasks = $this->getTasks($tenant);
        $syncProfile = AccountingSyncProfile::where('integration', 2)->one();
        $account = NetSuiteAccount::one();

        foreach ($tasks as $task) {
            $record = Payment::find($task);
            if ($record === null) {
                $output->writeln("Payment with id {$task} not found");
                continue;
            }
            $netSuiteModel = $this->factory->create($record, $syncProfile);

            // if the entity is already map - we should not create it
            if (!$netSuiteModel->shouldCreate()) {
                $output->writeln("should not Create");
                continue;
            }

            try {
                $data = $netSuiteModel->toArray();
                if (!$data) {
                    $output->writeln("No data");
                    continue;
                }
                $response = $this->netSuiteApi->callRestlet($account, 'post', $netSuiteModel, $data);
                if ($response) {
                    $netSuiteModel->skipReconciliation();
                    $output->writeln((string) json_encode($response));
                    if (!property_exists($response, 'id') || !$response->id) {
                        $output->writeln("No id");
                    }
                    if ($mapping = AccountingMappingFactory::getInstance($record)) {
                        $mapping->setIntegration(IntegrationType::NetSuite);
                        $mapping->accounting_id = $response->id;
                        $mapping->source = (property_exists($response, 'existing') && $response->existing) ? AbstractMapping::SOURCE_ACCOUNTING_SYSTEM : AbstractMapping::SOURCE_INVOICED;
                        $mapping->save();
                    }
                }
                $this->handleSyncSuccess($record);
                $output->writeln("handleSyncSuccess");
            } catch (NetSuiteReconciliationException $e) {
                $output->writeln("NetSuiteReconciliationException");
                // NetSuite integration warnings are being hidden to the user
                if (ReconciliationError::LEVEL_WARNING != $e->getLevel()) {
                    $output->writeln("not LEVEL_WARNING");
                    $this->handleSyncException($record, $syncProfile->getIntegrationType(), $e->getMessage(), ModelCreated::getName(), $e->getLevel());
                }
            } catch (IntegrationApiException $e) {
                $output->writeln("IntegrationApiException");
                $this->handleSyncException($record, $syncProfile->getIntegrationType(), $e->getMessage(), ModelCreated::getName());
            }
        }


        $this->tenant->clear();

        return 0;
    }



    /**
     * Handles a successful posted payment.
     */
    protected function handleSyncSuccess(AccountingWritableModelInterface $record): void
    {
        // delete any previous reconciliation errors for this transaction
        /* @phpstan-ignore-next-line */
        ReconciliationError::where('object', $record->object)
            ->where('object_id', $record)
            ->delete();
    }

    /**
     * Handles an exception during the payment posting process.
     */
    protected function handleSyncException(AccountingWritableModelInterface $record, IntegrationType $integrationType, string $message, string $eventName, string $level = ReconciliationError::LEVEL_ERROR): void
    {
        ReconciliationError::makeWriteError(
            $integrationType->value,
            $record->getAccountingObjectReference(),
            $message,
            $eventName,
            $level
        );

    }

}
