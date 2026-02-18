<?php

namespace App\Integrations\Plaid\Libs;

use App\CashApplication\Models\CashApplicationBankAccount;
use App\Companies\Models\Company;
use App\Core\Utils\DebugContext;
use App\Integrations\Interfaces\WebhookHandlerInterface;
use App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy\PlaidTransactionWebhookStrategyInterface;
use App\Integrations\Plaid\Models\PlaidItem;
use App\Integrations\Traits\IntegrationLogAwareTrait;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * This webhook is for interfacing with events from the Plaid Transactions product.
 */
class PlaidTransactionWebhook implements WebhookHandlerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use IntegrationLogAwareTrait;

    private CashApplicationBankAccount $account;
    /**
     * @var PlaidTransactionWebhookStrategyInterface[]
     */
    private array $strategies;

    public function __construct(
        private readonly CloudWatchLogsClient $cloudWatchLogsClient,
        private readonly DebugContext $debugContext
    ) {
    }

    public function setStrategies(PlaidTransactionWebhookStrategyInterface ...$strategies): void
    {
        $this->strategies = $strategies;
    }

    //
    // WebhookHandlerInterface
    //

    public function shouldProcess(array &$event): bool
    {
        return true;
    }

    public function getCompanies(array $event): array
    {
        if (empty($event['item_id'])) {
            return [];
        }

        $itemId = $event['item_id'];

        $plaidItem = PlaidItem::queryWithoutMultitenancyUnsafe()
            ->where('item_id', $itemId)
            ->oneOrNull();

        if (!($plaidItem instanceof PlaidItem)) {
            return [];
        }

        $tenant = $plaidItem->tenant();
        $account = CashApplicationBankAccount::queryWithTenant($tenant)
            ->where('plaid_link_id', $plaidItem->id)
            ->oneOrNull();
        if (!($account instanceof CashApplicationBankAccount)) {
            return [];
        }

        $this->account = $account;

        return [$tenant];
    }

    public function process(Company $company, array $event): void
    {
        if (!isset($this->account)) {
            throw new Exception('No bank account link model for given item id: '.$event['item_id']);
        }

        $logger = $this->makeIntegrationLogger('plaid', $company, $this->cloudWatchLogsClient, $this->debugContext);
        $logger->info((string) json_encode($event));

        foreach ($this->strategies as $strategy) {
            if ($strategy->match($event['webhook_code'])) {
                $company->useTimezone();
                $strategy->process($event, $this->account);
            }
        }
    }
}
