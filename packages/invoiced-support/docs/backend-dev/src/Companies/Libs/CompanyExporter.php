<?php

namespace App\Companies\Libs;

use App\AccountsReceivable\Interfaces\HasShipToInterface;
use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Item;
use App\AccountsReceivable\Models\LineItem;
use App\AccountsReceivable\Models\Note;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\AccountsReceivable\Models\ShippingDetail;
use App\AccountsReceivable\Models\ShippingRate;
use App\CashApplication\Models\CreditBalanceAdjustment;
use App\CashApplication\Models\Payment;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\Models\Task;
use App\Companies\Models\Company;
use App\Core\ListQueryBuilders\ListQueryBuilderFactory;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\InfuseUtility as U;
use App\Core\Utils\ZipUtil;
use App\Exports\Exporters\AbstractExporter;
use App\Exports\Libs\ExportStorage;
use App\Exports\Models\Export;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Metadata\Libs\AttributeHelper;
use App\Metadata\Storage\AttributeStorage;
use App\Metadata\Storage\LegacyMetadataStorage;
use App\Metadata\ValueObjects\Attribute;
use App\Metadata\ValueObjects\AttributeMoney;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\PaymentProcessing\Interfaces\HasPaymentSourceInterface;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\SalesTax\Models\TaxRate;
use App\SubscriptionBilling\Models\CouponRedemption;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Models\SubscriptionAddon;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Generator;
use stdClass;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class CompanyExporter extends AbstractExporter
{
    private const MAX_CHUNK = 1000000;

    /** @var class-string<MultitenantModel>[] */
    private static array $exportModels = [
        ChasingCadence::class,
        Contact::class,
        Coupon::class,
        CreditBalanceAdjustment::class,
        CreditNote::class,
        Customer::class,
        Estimate::class,
        Invoice::class,
        Item::class,
        Note::class,
        Payment::class,
        PaymentPlan::class,
        Plan::class,
        Subscription::class,
        Task::class,
        TaxRate::class,
    ];

    private array $metadataAttributesCache = [];
    private array $ratesCache = [
        'coupon' => [],
        'tax_rate' => [],
        'shipping_rate' => [],
    ];
    private array $cardsCache = [];
    private array $accountCache = [];
    private int $counter = 0;

    private string $tempDir;

    /** @var class-string<MultitenantModel> */
    private string $model;
    private int $chunk = 1;
    protected int $progressCounter = 0;

    public function __construct(
        ExportStorage $storage,
        private readonly string $projectDir,
        private readonly Connection $connection,
        private readonly AttributeHelper $helper,
        ListQueryBuilderFactory $listQueryFactory,
    ) {
        $this->tempDir = $this->projectDir.'/var/exports';
        @mkdir($this->tempDir);

        parent::__construct($storage, $connection, $helper, $listQueryFactory);
    }

    public function build(Export $export, array $options): void
    {
        $company = $export->tenant();

        $this->cacheValues($company);

        $total = 0;
        foreach (self::$exportModels as $model) {
            $total += $model::queryWithTenant($company)->count();
        }
        $export->incrementTotalRecords($total);

        // build data
        foreach (self::$exportModels as $model) {
            $this->model = $model;
            $this->chunk = 1;
            $this->metadataAttributesCache = [];

            $filename = $this->getFileLabel($export).'json';
            $h = $this->openFile($filename);

            foreach ($this->getCommonLine($company, $model) as $data) {
                ++$this->progressCounter;
                if ($this->counter > self::MAX_CHUNK) {
                    $this->closeFile($export, $company, $h, $filename);
                    ++$this->chunk;
                    $this->counter = 0;
                    $filename = $this->getFileLabel($export).'json';
                    $h = $this->openFile($filename);
                }
                // this will prevent export for being marked as failed
                if ($this->progressCounter > 10000) {
                    $this->incrementPosition($export);
                }
                ++$this->counter;
                fwrite($h, $data);
                fwrite($h, ",\n");
            }
            $this->closeFile($export, $company, $h, $filename);
        }

        $this->finish($export);
    }

    private function openFile(string $filename): mixed
    {
        $this->progressCounter = 0;
        $h = fopen($filename, 'w');
        if (!$h) {
            throw new FileException("Can't open file $filename for write");
        }
        // double-spacing fixes issue when list it empty and seek minus 2
        fwrite($h, "[\n\n");

        return $h;
    }

    private function getFileLabel(Export $export): string
    {
        return $this->model::modelName().'s '.$export->tenant_id.'('.$this->chunk.').';
    }

    protected function getFileName(Export $export): string
    {
        return $this->getFileLabel($export).'zip';
    }

    private function closeFile(Export $export, Company $company, mixed $h, string $filename): void
    {
        // clean up last comma for valid json
        fseek($h, -2, SEEK_END);
        fwrite($h, "\n]");
        fclose($h);
        $this->incrementPosition($export);
        // create the zip file
        $tmpFilename = $this->tempDir.'/'.strtolower(U::guid()).'.zip';
        $result = ZipUtil::createZip([$filename], $this->tempDir, '.'.$company->id(), $tmpFilename);
        if (!$result) {
            throw new FileException("Can't create zip file");
        }
        $this->persist($export, $result);
        // clean up pre-zipped files
        @unlink($filename);
        @unlink($tmpFilename);
    }

    public function incrementPosition(Export $export): void
    {
        $export->incrementPosition($this->progressCounter);
        $this->progressCounter = 0;
    }

    /**
     * @param ReceivableDocument[]|LineItem[] $models
     */
    private function hydrateAppliedRates(array $models): void
    {
        $first = current($models);
        if (!$first) {
            return;
        }
        $key = $first instanceof LineItem
            ? 'line_item_id'
            : ObjectType::fromModel($first)->typeName().'_id';

        $ids = array_keys($models);

        $qb = $this->connection->createQueryBuilder();
        $items = $qb
            ->select('*')
            ->from('AppliedRates')
            ->andWhere($qb->expr()->in($key, ':ids'))
            ->setParameter('ids', $ids, ArrayParameterType::INTEGER)
            ->andWhere('tenant_id = :tenant')
            ->setParameter('tenant', $first->tenant()->id)
            ->addOrderBy('`order`')
            ->addOrderBy('id')
            ->fetchAllAssociative();

        $couponName = ObjectType::fromModelClass(Coupon::class)->typeName();
        $taxName = ObjectType::fromModelClass(TaxRate::class)->typeName();
        $shippingName = ObjectType::fromModelClass(ShippingRate::class)->typeName();

        $itemsCache = [
            $couponName => array_fill_keys($ids, []),
            $taxName => array_fill_keys($ids, []),
            $shippingName => array_fill_keys($ids, []),
        ];
        foreach ($items as $item) {
            unset($item['rate']);
            unset($item['created_at']);
            $type = 'discount' === $item['type'] ? $couponName : ('tax' === $item['type'] ? $taxName : $shippingName);
            $item[$type] = $this->ratesCache[$type][$item['id']] ?? null;
            $itemsCache[$type][$item[$key]][] = $item;
        }
        array_walk($models, function (ReceivableDocument|LineItem $item) use ($itemsCache, $couponName, $taxName, $shippingName) {
            $item->discounts = $itemsCache[$couponName][$item->id];
            $item->taxes = $itemsCache[$taxName][$item->id];
            if (!($item instanceof LineItem)) {
                $item->shipping = $itemsCache[$shippingName][$item->id];
            }
        });
    }

    private function cacheValues(Company $company): void
    {
        // caching tax rate model for future reuse
        $res = Coupon::queryWithTenant($company)->limit(10000000)->all();
        $itemsCache = [];
        foreach ($res as $item) {
            $itemsCache[$item->id()] = $item;
        }
        $this->hydrateMetadata($company, $itemsCache);
        foreach ($itemsCache as $item) {
            $this->ratesCache['coupon'][$item->id()] = $item;
        }

        $res = TaxRate::queryWithTenant($company)->limit(10000000)->all();
        $itemsCache = [];
        foreach ($res as $item) {
            $itemsCache[$item->id()] = $item;
        }
        $this->hydrateMetadata($company, $itemsCache);
        foreach ($itemsCache as $item) {
            $this->ratesCache['tax_rate'][$item->id()] = $item;
        }

        $res = ShippingRate::queryWithTenant($company)->limit(10000000)->all();
        $itemsCache = [];
        foreach ($res as $item) {
            $itemsCache[$item->id()] = $item;
        }
        $this->hydrateMetadata($company, $itemsCache);
        foreach ($itemsCache as $item) {
            $this->ratesCache['shipping_rate'][$item->id()] = $item;
        }

        $res = Card::queryWithTenant($company)->limit(10000000)->all();
        foreach ($res as $item) {
            $this->cardsCache[$item->id()] = $item;
        }

        $res = BankAccount::queryWithTenant($company)->limit(10000000)->all();
        foreach ($res as $item) {
            $this->accountCache[$item->id()] = $item;
        }
    }

    /**
     * @param Item[] $chunk
     */
    private function hydrateItem(array $chunk): void
    {
        // we need to re cache the internal id based data to id
        $taxRateByTextId = [];
        foreach ($this->ratesCache['tax_rate'] as $rate) {
            $taxRateByTextId[$rate['id']] = $rate;
        }
        array_walk($chunk, function (Item $item) use ($taxRateByTextId) {
            if ($item->archived || !$item->taxes) {
                return;
            }
            $item->taxes = array_map(fn ($taxId) => $taxRateByTextId[$taxId], $item->taxes);
        });
    }

    /**
     * @param ReceivableDocument[] $chunk
     */
    private function decorateReceivableDocument(Company $company, array $chunk): void
    {
        $this->hydrateAppliedRates($chunk);

        $key = ObjectType::fromModel(current($chunk))->typeName().'_id';

        /** @var LineItem[] $items */
        $items = LineItem::queryWithTenant($company)
            ->where($key.' IN ('.implode(',', array_keys($chunk)).')')
            ->sort('order ASC')
            ->limit(100000)
            ->all();

        $lineItemsCache = [];
        foreach ($items as $item) {
            $lineItemsCache[$item->id] = $item;
        }
        $this->hydrateAppliedRates($lineItemsCache);
        $this->hydrateMetadata($company, $lineItemsCache);

        $itemsCache = array_fill_keys(array_keys($chunk), []);
        foreach ($lineItemsCache as $item) {
            $itemsCache[$item->{$key}][] = $item;
        }

        array_walk($chunk, function (ReceivableDocument $doc) use ($itemsCache) {
            $doc->items = $itemsCache[$doc->id()];
        });
    }

    /**
     * @param PaymentPlan[] $chunk
     */
    private function decoratePaymentPlan(Company $company, array $chunk): void
    {
        /** @var PaymentPlanInstallment[] $installments */
        $installments = PaymentPlanInstallment::queryWithTenant($company)
            ->where('payment_plan_id IN ('.implode(',', array_keys($chunk)).')')
            ->sort('date ASC')
            ->limit(100000)
            ->all();
        $installmentsCache = array_fill_keys(array_keys($chunk), []);
        foreach ($installments as $installment) {
            $installmentsCache[$installment->payment_plan_id][] = $installment;
        }
        // prevent reload
        array_walk($chunk, fn (PaymentPlan $item) => $item->installments = $installmentsCache[$item->id]);
    }

    /**
     * @param Subscription[] $chunk
     */
    private function decorateSubscription(Company $company, array $chunk): void
    {
        $addons = SubscriptionAddon::queryWithTenant($company)
            ->where('subscription_id IN ('.implode(',', array_keys($chunk)).')')
            ->limit(100000)
            ->all();
        $cache = array_fill_keys(array_keys($chunk), []);
        foreach ($addons as $addon) {
            $cache[$addon->subscription_id][] = $addon;
        }
        // prevent reload
        array_walk($chunk, fn (Subscription $item) => $item->setAddons($cache[$item->id]));

        /** @var CouponRedemption[] $redemptions */
        $redemptions = CouponRedemption::queryWithTenant($company)
            ->where('parent_id IN ('.implode(',', array_keys($chunk)).')')
            ->where('parent_type', 'subscription')
            ->limit(100000)
            ->all();
        $cache = array_fill_keys(array_keys($chunk), []);
        foreach ($redemptions as $redemption) {
            $redemption->setCoupon($this->ratesCache['coupon'][$redemption->coupon_id]);
            $cache[$redemption->parent_id][] = $redemption;
        }
        // prevent reload
        array_walk($chunk, fn (Subscription $item) => $item->setCouponRedemptions($cache[$item->id]));
    }

    /**
     * @param ChasingCadence[] $chunk
     */
    private function decorateChasingCadence(Company $company, array $chunk): void
    {
        /** @var ChasingCadenceStep[] $steps */
        $steps = ChasingCadenceStep::queryWithTenant($company)
            ->where('chasing_cadence_id IN ('.implode(',', array_keys($chunk)).')')
            ->sort('order ASC')
            ->limit(100000)
            ->all();
        $stepsCache = array_fill_keys(array_keys($chunk), []);
        foreach ($steps as $step) {
            $stepsCache[$step->chasing_cadence_id][] = $step;
        }
        // prevent reload
        array_walk($chunk, fn ($item) => $item->hydrateSteps($stepsCache[$item->id]));
    }

    /**
     * @param HasPaymentSourceInterface[] $chunk
     */
    private function hydratePaymentSource(array $chunk): void
    {
        array_walk($chunk, function (HasPaymentSourceInterface $item) {
            if ('bank_account' === $item->getPaymentSourceType()) {
                $item->setPaymentSource($this->accountCache[$item->getPaymentSourceId()]);
            } elseif ('card' === $item->getPaymentSourceType()) {
                $item->setPaymentSource($this->cardsCache[$item->getPaymentSourceId()]);
            }
        });
    }

    /**
     * @param HasShipToInterface[] $chunk
     */
    private function hydrateShipTo(Company $company, array $chunk): void
    {
        if (!$chunk) {
            return;
        }
        /** @var MultitenantModel $first */
        $first = current($chunk);
        $key = ObjectType::fromModel($first)->typeName().'_id';
        /** @var ShippingDetail[] $shipping */
        $shipping = ShippingDetail::queryWithTenant($company)
            ->where($key.' IN ('.implode(',', array_keys($chunk)).')')
            ->limit(100000)
            ->all();
        $cache = [];
        foreach ($shipping as $item) {
            $cache[$item->{$key}] = $item;
        }
        array_walk($chunk, fn (HasShipToInterface $item) => $item->hydrateShipTo($cache[$item->id()] ?? null));
    }

    private function getCommonLine(Company $company, string $model): Generator
    {
        // fetching from cache
        if (is_a($model, TaxRate::class, true)) {
            foreach ($this->ratesCache['tax_rate'] as $item) {
                yield json_encode($item->toArray(), JSON_PRETTY_PRINT);
            }

            return;
        }
        if (is_a($model, Coupon::class, true)) {
            foreach ($this->ratesCache['coupon'] as $item) {
                yield json_encode($item->toArray(), JSON_PRETTY_PRINT);
            }

            return;
        }

        $lastId = 0;
        while (true) {
            $qry = $model::queryWithTenant($company)
                ->where('id', $lastId, '>')
                ->sort('id ASC');

            if (is_a($model, Note::class, true)) {
                $qry->with('user_id');
            }
            if (is_a($model, Payment::class, true)) {
                $qry->with('charge');
            }
            if (is_a($model, PaymentPlan::class, true)
                || is_a($model, Estimate::class, true)
                || is_a($model, Subscription::class, true)) {
                $qry->with('approval_id');
            }

            $finder = $qry->first(100000);
            if (!count($finder)) {
                break;
            }
            $chunk = [];
            foreach ($finder as $item) {
                $chunk[$item->id()] = $item;
            }

            if (is_a($model, ReceivableDocument::class, true)) {
                $this->decorateReceivableDocument($company, $chunk);
            }
            if (is_a($model, Item::class, true)) {
                $this->hydrateItem($chunk);
            }
            if (is_a($model, ChasingCadence::class, true)) {
                $this->decorateChasingCadence($company, $chunk);
            }
            if (is_a($model, MetadataModelInterface::class, true)) {
                $this->hydrateMetadata($company, $chunk);
            }
            if (is_a($model, HasPaymentSourceInterface::class, true)) {
                $this->hydratePaymentSource($chunk);
            }
            if (is_a($model, HasShipToInterface::class, true)) {
                $this->hydrateShipTo($company, $chunk);
            }
            if (is_a($model, PaymentPlan::class, true)) {
                $this->decoratePaymentPlan($company, $chunk);
            }
            if (is_a($model, Subscription::class, true)) {
                $this->decorateSubscription($company, $chunk);
            }
            foreach ($chunk as $item) {
                $lastId = $item->id;
                $data = json_encode($item->toArray(), JSON_PRETTY_PRINT);
                if (!$data) {
                    throw new FileException("Can't export model ".$item::class." with id: $item->id");
                }
                yield $data;
            }
        }
    }

    /**
     * @param MetadataModelInterface[] $chunk
     */
    private function hydrateMetadata(Company $company, array $chunk): void
    {
        if (!count($chunk)) {
            return;
        }
        // prevent metadata reload
        array_walk($chunk, fn ($item) => $item->hydrateMetadata(new stdClass()));
        $first = current($chunk);
        $writers = $first->getMetadataWriters();
        if (count($writers)) {
            if (count(array_filter($writers, fn ($writer) => $writer instanceof AttributeStorage))) {
                $this->batchDecorateAttributeMetadata($chunk);
            } elseif (count(array_filter($writers, fn ($writer) => $writer instanceof LegacyMetadataStorage))) {
                $this->batchDecorateLegacyMetadata($company, $chunk);
            }
        }
    }

    /**
     * @param MetadataModelInterface[] $chunk
     */
    private function batchDecorateLegacyMetadata(Company $company, array $chunk): void
    {
        /** @var MetadataModelInterface $first */
        $first = current($chunk);
        $objectName = $first->getObjectName();
        $metadata = $this->connection->executeQuery(
            'SELECT `key`,`value`,object_id FROM Metadata WHERE tenant_id = ? AND object_type = ? AND object_id IN (?)',
            [
                $company->id,
                $objectName,
                array_map(fn ($item) => $item->id(), $chunk),
            ],
            [
                ParameterType::INTEGER,
                ParameterType::STRING,
                ArrayParameterType::INTEGER,
            ]
        )->fetchAllAssociative();
        foreach ($metadata as $metadataItem) {
            $chunk[$metadataItem['object_id']]->metadata->{$metadataItem['key']} = $metadataItem['value'];
        }
    }

    /**
     * @param MetadataModelInterface[] $chunk
     */
    private function batchDecorateAttributeMetadata(array $chunk): void
    {
        /** @var MetadataModelInterface $first */
        $first = current($chunk);
        $this->helper->build($first);

        // build attribute cache during first iteration
        if (0 === count($this->metadataAttributesCache)) {
            $attributes = $this->helper->getAllAttributes();
            // now regroup them by type
            foreach ($attributes as $attribute) {
                $attribute->setModel($first);
                $table = $attribute->getTable();
                if (!isset($this->metadataAttributesCache[$table])) {
                    $this->metadataAttributesCache[$table] = [];
                }
                $this->metadataAttributesCache[$table][$attribute->getId()] = $attribute;
            }
        }

        /**
         * @var Attribute[] $attributes
         */
        foreach ($this->metadataAttributesCache as $table => $attributes) {
            $current = current($attributes);
            if ($current instanceof AttributeMoney) {
                $sql = "SELECT attribute_id, currency, value, object_id FROM $table WHERE attribute_id IN (?) AND object_id IN(?)";
            } else {
                $sql = "SELECT attribute_id, value, object_id FROM $table WHERE attribute_id IN (?) AND object_id IN(?)";
            }
            $metadata = $this->connection->executeQuery($sql,
                [
                    array_keys($attributes),
                    array_keys($chunk),
                ],
                [
                    ArrayParameterType::INTEGER,
                    ArrayParameterType::INTEGER,
                ]
            )->fetchAllAssociative();

            foreach ($metadata as $metadataItem) {
                $attribute = $attributes[$metadataItem['attribute_id']];
                $format = $attribute->format([
                    'name' => $attribute->getName(),
                    'value' => $metadataItem['value'],
                    'currency' => $metadataItem['currency'] ?? null,
                ]);
                $chunk[$metadataItem['object_id']]->metadata->{$format['key']} = $format['value'];
            }
        }
    }

    public static function getId(): string
    {
        return 'company_json';
    }

    public static function getClass(): string
    {
        return Company::class;
    }
}
