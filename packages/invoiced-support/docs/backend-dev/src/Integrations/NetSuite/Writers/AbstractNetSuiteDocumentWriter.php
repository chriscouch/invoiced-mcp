<?php

namespace App\Integrations\NetSuite\Writers;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\Exception\MismatchedCurrencyException;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Traits\LineItemsMapperTrait;
use App\Integrations\NetSuite\Exceptions\NetSuiteReconciliationException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use Carbon\CarbonImmutable;

/**
 * @template-extends    AbstractNetSuiteCustomerObjectWriter<ReceivableDocument>
 *
 * @phpstan-template T of AccountingWritableModelInterface
 *
 * @property ReceivableDocument $model
 */
abstract class AbstractNetSuiteDocumentWriter extends AbstractNetSuiteCustomerObjectWriter
{
    use LineItemsMapperTrait;

    public function __construct(AccountingWritableModelInterface $model, protected readonly AccountingSyncProfile $profile)
    {
        parent::__construct($model);
    }

    protected function getParentCustomer(): Customer
    {
        return $this->model->customer();
    }

    public function shouldUpdate(): bool
    {
        if (!$this->shouldSend()) {
            return false;
        }

        // update only if reverse mapping is not set
        return null === $this->getReverseMapping() || $this->model->voided;
    }

    public function shouldCreate(): bool
    {
        if (!$this->shouldSend()) {
            return false;
        }

        // update only if reverse mapping is not set
        return parent::shouldCreate();
    }

    private function shouldSend(): bool
    {
        $model = $this->model;

        if ($model->draft) {
            return false;
        }

        return !$this->profile->invoice_start_date || CarbonImmutable::createFromTimestamp($this->profile->invoice_start_date)->startOfDay()->lessThanOrEqualTo(CarbonImmutable::createFromTimestamp($model->date)->startOfDay());
    }

    /**
     * @throws MismatchedCurrencyException
     * @throws NetSuiteReconciliationException
     * @throws ReconciliationException
     */
    public function toArray(): array
    {
        $this->decorateLineItems();

        $data = parent::toArray();
        $currency = $this->model->currency;
        $discounts = Money::zero($currency);
        $taxes = Money::zero($currency);
        foreach ($this->model->discounts as $discount) {
            $discounts = $discounts->add(Money::fromDecimal($currency, $discount->amount));
        }
        foreach ($this->model->taxes as $tax) {
            $taxes = $taxes->add(Money::fromDecimal($currency, $tax->amount));
        }

        $data['discountrate'] = -1 * $discounts->toDecimal();
        $data['discountitem'] = $this->profile->parameters->discountitem ?? null;

        if ($data['discountrate'] && !$data['discountitem']) {
            throw new NetSuiteReconciliationException('Discount item is required for discount rate');
        }

        $data['taxrate'] = $taxes->toDecimal();
        $data['taxlineitem'] = $this->profile->parameters->taxlineitem ?? null;
        $data['location'] = $this->profile->parameters->location ?? null;

        if ($data['discountrate'] && !$data['discountitem']) {
            throw new NetSuiteReconciliationException('Tax item is required for tax rate');
        }

        return $data;
    }
}
