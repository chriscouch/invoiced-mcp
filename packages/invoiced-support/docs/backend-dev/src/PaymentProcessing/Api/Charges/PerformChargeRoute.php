<?php

namespace App\PaymentProcessing\Api\Charges;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Model;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Libs\ApiKeyAuth;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Utils\Enums\ObjectType;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Interfaces\ChargeApplicationItemInterface;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Operations\ProcessPayment;
use App\PaymentProcessing\Operations\VaultPaymentInfo;
use App\PaymentProcessing\ValueObjects\AppliedCreditChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\CreditChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\CreditNoteChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\EstimateChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\InvoiceChargeApplicationItem;
use Symfony\Component\HttpFoundation\Response;

/**
 * API endpoint to perform charges through the payment system.
 */
class PerformChargeRoute extends AbstractRetrieveModelApiRoute
{
    private const ALLOWED_CHARGE_PARAMETERS = [
        'invoiced_token',
        'gateway_token',
        'receipt_email',
        'payment_method',
        'payment_card', // used for adyen card payment, because adyen is giving encrypted card data in FE
        // Used for Card
        'cvc',
        // Used for ACH Direct Debit
        'account_holder_name',
        'account_holder_type',
        'account_number',
        'routing_number',
    ];

    private const ALLOWED_SPLIT_TYPES = [
        PaymentItemType::Invoice->value,
        PaymentItemType::Estimate->value,
        PaymentItemType::Credit->value,
        PaymentItemType::AppliedCredit->value,
        PaymentItemType::CreditNote->value,
    ];
    private Money $amount;
    private string $paymentSourceType = '';
    private int $paymentSourceId;
    private string $methodId;
    private array $chargeParameters;
    private array $splits = [];
    private bool $vaultMethod = false;
    private bool $makeDefault = false;
    private bool $applyConvenienceFee = true;

    public function __construct(
        private ApiKeyAuth $apiKeyAuth,
        private TenantContext $tenant,
        private ProcessPayment $processPayment,
        private VaultPaymentInfo $vaultPaymentInfo,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['charges.create'],
            modelClass: Customer::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): Payment|Response
    {
        // parse the customer
        $this->setModelId((string) $context->request->request->get('customer'));

        // parse the method
        $this->methodId = (string) $context->request->request->get('method');

        $this->applyConvenienceFee = $context->request->request->getBoolean('apply_convenience_fee', true);

        // parse the amount
        $currency = ((string) $context->request->request->get('currency')) ?: $this->tenant->get()->currency;
        $amountInput = (float) $context->request->request->get('amount');
        $amount = Money::fromDecimal($currency, $amountInput);
        $this->setAmount($amount);

        // parse the payment source
        if ($paymentSourceType = $context->request->request->get('payment_source_type')) {
            $this->paymentSourceType = (string) $paymentSourceType;
            $this->paymentSourceId = (int) $context->request->request->get('payment_source_id');
        }
        $this->vaultMethod = (bool) $context->request->request->get('vault_method');
        $this->makeDefault = (bool) $context->request->request->get('make_default');

        // parse the splits
        if ($splits = $context->request->request->all('applied_to')) {
            $this->splits = $splits;
        } else {
            // this is a legacy request format that is transformed to maintain BC
            $splits = $context->request->request->all('splits');
            foreach ($splits as &$split) {
                if (isset($split['type']) && 'advance' == $split['type']) {
                    $split['type'] = PaymentItemType::Credit->value;
                }
            }
            $this->splits = $splits;
        }

        // parse the charge parameters
        $this->chargeParameters = [];
        foreach (self::ALLOWED_CHARGE_PARAMETERS as $k) {
            if ($value = $context->request->request->get($k)) {
                $this->chargeParameters[$k] = $value;
            }
        }

        /** @var Customer $customer */
        $customer = $this->retrieveModel($context);

        // look up the payment method and payment source
        $paymentSource = $this->getPaymentSource($customer);

        // verify the cvc code
        if ($paymentSource && $paymentSource instanceof Card && !isset($this->chargeParameters['cvc']) && $customer->tenant()->accounts_receivable_settings->saved_cards_require_cvc) {
            throw new InvalidRequest('Please enter in the required CVC code');
        }

        // parse the splits
        $amountSplits = $this->parseSplits();

        // save the payment info for later
        if ($this->vaultMethod) {
            $method = $this->getPaymentMethod();
            $paymentSource = $this->vaultMethod($method, $customer, $this->chargeParameters, $this->makeDefault);
        }

        // When there is a user on the API key then we can assume this
        // is being processed through the dashboard AKA virtual terminal
        $source = !$this->apiKeyAuth->getCurrentApiKey()?->user() ? PaymentFlowSource::Api : PaymentFlowSource::VirtualTerminal;

        // Set additional parameters on the payment
        $paymentValues = [];
        if ($notes = $context->request->request->get('notes')) {
            $paymentValues['notes'] = $notes;
        }

        $requestParameters = $context->request->request->all();
        $metadata = $requestParameters['metadata'] ?? null;
        if ($metadata) {
            $paymentValues['metadata'] = $metadata;
        }
        $chargeApplication = new ChargeApplication($amountSplits, $source, $paymentValues);

        // and finally perform the charge
        try {
            if ($paymentSource) {
                $payment = $this->processPayment->payWithSource($paymentSource, $chargeApplication, $this->chargeParameters, null, $this->applyConvenienceFee);
            } else {
                $method = $this->getPaymentMethod();
                $payment = $this->processPayment->pay($method, $customer, $chargeApplication, $this->chargeParameters, null, $this->applyConvenienceFee);
            }
        } catch (ChargeException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        if (null === $payment) {
            return new Response('', 204);
        }

        return $payment;
    }

    //
    // Helpers
    //

    /**
     * Sets the charge amount.
     */
    public function setAmount(Money $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * Gets the payment amount.
     */
    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getChargeParameters(): array
    {
        return $this->chargeParameters;
    }

    public function shouldVaultMethod(): bool
    {
        return $this->vaultMethod;
    }

    public function shouldMakeDefault(): bool
    {
        return $this->makeDefault;
    }

    /**
     * Gets the payment method requested by the caller.
     *
     * @throws InvalidRequest
     */
    public function getPaymentMethod(): PaymentMethod
    {
        if (!$this->methodId) {
            throw new InvalidRequest('Payment method is missing. Please provide this with the `method` parameter.');
        }

        $company = $this->tenant->get();
        $method = PaymentMethod::instance($company, $this->methodId);

        return $method;
    }

    /**
     * Gets the payment source supplied, if there was one.
     */
    public function getPaymentSource(Customer $customer): ?PaymentSource
    {
        if (ObjectType::Card->typeName() == $this->paymentSourceType) {
            return Card::where('id', $this->paymentSourceId)
                ->where('customer_id', $customer)
                ->where('chargeable', true)
                ->oneOrNull();
        }

        if (ObjectType::BankAccount->typeName() == $this->paymentSourceType) {
            return BankAccount::where('id', $this->paymentSourceId)
                ->where('customer_id', $customer)
                ->where('chargeable', true)
                ->oneOrNull();
        }

        return null;
    }

    /**
     * Parses the document splits.
     *
     * @throws InvalidRequest
     *
     * @return ChargeApplicationItemInterface[]
     */
    public function parseSplits(): array
    {
        $paymentSplits = [];
        foreach ($this->splits as $split) {
            $type = $split['type'] ?? '';
            if (!in_array($type, self::ALLOWED_SPLIT_TYPES)) {
                throw new InvalidRequest('Invalid split '.$type.'. Allowed split types are: '.implode(',', self::ALLOWED_SPLIT_TYPES).'.');
            }

            if (!isset($split['amount'])) {
                throw new InvalidRequest('Amount must be specified on all splits.');
            }

            // look up associated document
            $associatedDocument = null;
            if (in_array($type, [PaymentItemType::CreditNote->value, PaymentItemType::AppliedCredit->value])) {
                if (!isset($split['document_type'])) {
                    throw new InvalidRequest('Split is missing value: document_type');
                }

                $documentType = $split['document_type'];
                if (!isset($split[$documentType])) {
                    throw new InvalidRequest('Split is missing value: '.$documentType);
                }

                $associatedDocument = $this->lookupModel($documentType, $split[$documentType]);
            }

            // parse the amount requested
            $amount = Money::fromDecimal($this->amount->currency, $split['amount']);

            // generate the charge application item
            if (PaymentItemType::Estimate->value == $type) {
                if (!isset($split['estimate'])) {
                    throw new InvalidRequest('Split is missing value: estimate');
                }

                /** @var Estimate $estimate */
                $estimate = $this->lookupModel($type, $split['estimate']);
                $paymentSplits[] = new EstimateChargeApplicationItem($amount, $estimate);
            } elseif (PaymentItemType::Invoice->value == $type) {
                if (!isset($split['invoice'])) {
                    throw new InvalidRequest('Split is missing value: invoice');
                }

                /** @var Invoice $invoice */
                $invoice = $this->lookupModel($type, $split['invoice']);
                $paymentSplits[] = new InvoiceChargeApplicationItem($amount, $invoice);
            } elseif (PaymentItemType::Credit->value == $type) {
                $paymentSplits[] = new CreditChargeApplicationItem($amount);
            } elseif (PaymentItemType::CreditNote->value == $type) {
                if (!isset($split['credit_note'])) {
                    throw new InvalidRequest('Split is missing value: credit_note');
                }

                $documentType = $split['document_type'];
                if (!$associatedDocument instanceof ReceivableDocument) {
                    throw new InvalidRequest('Could not find '.$documentType.': '.$split[$documentType]);
                }

                /** @var CreditNote $creditNote */
                $creditNote = $this->lookupModel('credit_note', $split['credit_note']);
                $paymentSplits[] = new CreditNoteChargeApplicationItem($amount, $creditNote, $associatedDocument);
            } elseif (PaymentItemType::AppliedCredit->value == $type) {
                $documentType = $split['document_type'];
                if (!$associatedDocument instanceof ReceivableDocument) {
                    throw new InvalidRequest('Could not find '.$documentType.': '.$split[$documentType]);
                }

                $paymentSplits[] = new AppliedCreditChargeApplicationItem($amount, $associatedDocument);
            }
        }

        return $paymentSplits;
    }

    /**
     * Saves a token as a payment source on the customer for future reuse.
     *
     * @throws InvalidRequest when the payment source cannot be saved as default
     */
    private function vaultMethod(PaymentMethod $method, Customer $customer, array $parameters, bool $makeDefault): PaymentSource
    {
        try {
            return $this->vaultPaymentInfo->save($method, $customer, $parameters, $makeDefault);
        } catch (PaymentSourceException $e) {
            // rethrow as a form exception
            throw new InvalidRequest($e->getMessage());
        }
    }

    /**
     * Gets a model given a type and id.
     */
    private function lookupModel(string $type, int $id): Model
    {
        $modelClass = ObjectType::fromTypeName($type)->modelClass();

        $model = $modelClass::find($id);
        if (!$model) {
            throw new InvalidRequest('Could not find '.$type.': '.$id);
        }

        return $model;
    }
}
