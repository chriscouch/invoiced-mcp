<?php

namespace App\Exports\Exporters;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Models\CreditBalance;
use App\Core\ListQueryBuilders\ListQueryBuilderFactory;
use App\Core\Orm\Model;
use App\Core\Orm\Query;
use App\Core\Utils\Enums\ObjectType;
use App\Exports\Libs\ExportStorage;
use App\Metadata\Libs\AttributeHelper;
use App\Reports\Libs\AgingReport;
use App\Reports\ValueObjects\AgingBreakdown;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCsvExporter<Customer>
 */
class CustomerExporter extends AbstractCsvExporter
{
    private AgingBreakdown $agingBreakdown;
    private array $bucketNames = [];
    private array $agingReport;

    public function __construct(
        ExportStorage $storage,
        Connection $database,
        AttributeHelper $attributeHelper,
        private TranslatorInterface $translator,
        ListQueryBuilderFactory $listQueryFactory
    ) {
        parent::__construct($storage, $database, $attributeHelper, $listQueryFactory);
    }

    protected function getQuery(array $options): Query
    {
        $listQueryBuilder = $this->listQueryFactory->get(Customer::class, $this->company, $options);
        $listQueryBuilder->setSort('name ASC');
        $this->agingBreakdown = AgingBreakdown::fromSettings($this->company->accounts_receivable_settings);

        return $listQueryBuilder->getBuildQuery();
    }

    protected function getColumns(): array
    {
        $columns = [
            'name',
            'number',
            'email',
            'autopay',
            'payment_terms',
            'type',
            'attention_to',
            'address1',
            'address2',
            'city',
            'state',
            'postal_code',
            'country',
            'tax_id',
            'phone',
            'owner',
            'chasing_cadence',
            'next_chase_step',
            'credit_balance',
            'credit_hold',
            'credit_limit',
            'created_at',
            'notes',
            'currency',
        ];

        // append aging buckets
        foreach ($this->agingBreakdown->getBuckets() as $i => $bucket) {
            $columns[] = 'age.'.$i;
            $this->bucketNames[$i] = $this->agingBreakdown->getBucketName($bucket, $this->translator, $this->company->getLocale());
        }
        $columns[] = 'balance';

        $metadataColumns = $this->getMetadataColumns(ObjectType::Customer);

        return array_merge($columns, $metadataColumns);
    }

    public function getCsvColumnLabel(string $field): string
    {
        if (str_starts_with($field, 'age.')) {
            $i = str_replace('age.', '', $field);

            return $this->bucketNames[$i];
        }

        return parent::getCsvColumnLabel($field);
    }

    protected function getCsvColumnValue(Model $model, string $field): string
    {
        if ($model instanceof Customer) {
            if ('owner' == $field) {
                return $model->owner?->email ?: '';
            }

            if ('credit_balance' == $field) {
                return (string) CreditBalance::lookup($model)->toDecimal();
            }

            if ('chasing_cadence' == $field) {
                return $model->chasingCadence()?->name ?: '';
            }

            if ('next_chase_step' == $field) {
                return $model->nextChaseStep()?->name ?: '';
            }

            if ('currency' == $field) {
                return $this->getAgingReport($model)[0]['amount']->currency;
            }

            if ('balance' == $field) {
                $balance = null;
                foreach ($this->getAgingReport($model) as $row) {
                    if (!$balance) {
                        $balance = $row['amount'];
                    } else {
                        $balance = $balance->add($row['amount']);
                    }
                }

                return (string) $balance->toDecimal();
            }

            if (str_starts_with($field, 'age.')) {
                $i = str_replace('age.', '', $field);

                return (string) $this->getAgingReport($model)[$i]['amount']->toDecimal();
            }
        }

        return parent::getCsvColumnValue($model, $field);
    }

    private function getAgingReport(Customer $customer): array
    {
        $key = $customer->id();
        if (!isset($this->agingReport[$key])) {
            $aging = new AgingReport($this->agingBreakdown, $this->company, $this->database);
            $currency = $customer->calculatePrimaryCurrency();
            $this->agingReport = $aging->buildForCustomer($customer->id, $currency);
        }

        return $this->agingReport[$key];
    }

    public static function getId(): string
    {
        return 'customer_csv';
    }
}
