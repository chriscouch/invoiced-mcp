<?php

namespace App\Integrations\PayPal\Libs;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Models\Transaction;
use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Model;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\Enums\ObjectType;
use App\Integrations\Interfaces\WebhookHandlerInterface;
use App\Integrations\Libs\IpnContext;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Libs\ChargeApplicationBuilder;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Libs\PaymentGatewayMetadata;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Reconciliation\ChargeReconciler;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use App\PaymentProcessing\ValueObjects\PaymentFormItem;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Lock\LockFactory;

class PayPalWebhook implements WebhookHandlerInterface, LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    private const LOCK_TTL = 2592000; // 30 days in seconds

    private const WEB_ACCEPT = 'web_accept';

    private const PENDING_STATUS = 'Pending';
    private const COMPLETED_STATUS = 'Completed';

    private const OBJECT_NAMES = [
        'i' => 'invoice',
        'c' => 'credit_note',
        'e' => 'estimate',
        'a' => 'customer',
    ];

    public function __construct(
        private LockFactory $lockFactory,
        private string $environment,
        private IpnContext $payPalIpnContext,
        private ChargeReconciler $chargeReconciler,
    ) {
    }

    //
    // WebhookHandlerInterface
    //

    public function shouldProcess(array &$event): bool
    {
        // check if the IPN test mode matches our app environment
        $isProduction = 'production' == $this->environment;
        if (($event['test_ipn'] ?? false) == $isProduction) {
            return false;
        }

        // verify the event has not been processed before
        $ipnId = $this->buildIpnId($event);
        $key = $this->getEventLabel($ipnId);
        $lock = $this->lockFactory->createLock($key, self::LOCK_TTL, false);
        if (!$lock->acquire()) {
            return false;
        }

        return true;
    }

    private function getEventLabel(string $ipnId): string
    {
        return $this->environment.':paypal_ipn.'.$ipnId;
    }

    public function getCompanies(array $event): array
    {
        // decrypt company id
        $cid = $this->payPalIpnContext->decode($event['custom'] ?? '');

        $company = Company::findOrFail($cid);

        // verify that the user has paypal payments enabled
        $paypal = PaymentMethod::instance($company, PaymentMethod::PAYPAL);
        if (!$paypal->enabled()) {
            return [];
        }

        return [$company];
    }

    public function process(Company $company, array $event): void
    {
        // handle payments
        $txnType = $event['txn_type'] ?? '';
        if (self::WEB_ACCEPT == $txnType) {
            $this->handlePaymentEvent($event, $company);
        }
    }

    //
    // IPN Messages
    //

    private function buildIpnId(array $event): string
    {
        // PayPal IPNs do not include a unique identifier,
        // so we must invent one. Each transaction has a unique
        // ID (txn_id) with multiple states (payment_status).
        // Thus, we can derive a unique ID using these 2 values.
        return md5($event['txn_id'].'_'.($event['payment_status'] ?? ''));
    }

    //
    // PayPal Events
    //

    /**
     * Handles a payment event.
     */
    private function handlePaymentEvent(array $event, Company $company): void
    {
        // must be pending or completed
        $paypalStatus = $event['payment_status'];
        if (!in_array($paypalStatus, [self::PENDING_STATUS, self::COMPLETED_STATUS])) {
            return;
        }

        $ids = $this->parseInvoiceId($event['invoice'] ?? '');
        if (0 === count($ids)) {
            $this->logger->warning('Unable to reconcile charge in PayPal webhook', $event);

            return;
        }

        $currency = $event['mc_currency'];

        try {
            $form = $this->buildPaymentForm($company, $currency, $ids);
        } catch (FormException $e) {
            $this->logger->warning('Unable to reconcile charge in PayPal webhook', array_merge($event, ['exception' => $e]));

            return;
        }

        // update the customer's email address (if not already set)
        if (!$form->customer->email) {
            $form->customer->email = $event['payer_email'];
            $form->customer->save();
        }

        // calculate payment split across each document
        $chargeApplication = (new ChargeApplicationBuilder())
            ->addPaymentForm($form)
            ->build();

        // build the charge
        $description = GatewayHelper::makeDescription($chargeApplication->getDocuments());
        $charge = $this->buildCharge($event, $form->customer, $description);

        // then reconcile it
        try {
            $this->chargeReconciler->reconcile($charge, $chargeApplication, null, $event['payer_email']);
        } catch (ReconciliationException $e) {
            $this->logger->emergency('Unable to reconcile charge in PayPal webhook', ['exception' => $e]);
        }
    }

    /**
     * Parses the PayPal invoice ID into a list of documents
     * that the payment applies to.
     *
     * Example: i1234i456c789
     * References invoice # 1234, invoice # 456, and credit note # 789
     *
     * WARNING: This field has a 255 character limit
     */
    public function parseInvoiceId(string $input): array
    {
        $result = [];
        $numMatches = preg_match_all('/([aceis]{1})([\d]*)\|(-?[\d]+)/', $input, $matches);
        for ($i = 0; $i < $numMatches; ++$i) {
            // s means skip
            if ('s' == $matches[1][$i]) {
                continue;
            }

            $objectType = self::OBJECT_NAMES[$matches[1][$i]];
            $objectId = $matches[2][$i];
            $amount = (int) $matches[3][$i];
            $result[] = [$objectType, $objectId, $amount];
        }

        return $result;
    }

    /**
     * @throws FormException
     */
    private function buildPaymentForm(Company $company, string $currency, array $ids): PaymentForm
    {
        $paymentItems = [];
        $customer = null;
        foreach ($ids as $id) {
            [$objectType, $objectId, $amount] = $id;
            $document = null;
            if ($objectType) {
                /** @var Model $modelClass */
                $modelClass = ObjectType::fromTypeName($objectType)->modelClass();
                $model = $modelClass::find($objectId);
                if ($model instanceof ReceivableDocument) {
                    $document = $model;
                } elseif ($model instanceof Customer) {
                    $customer = $model;
                }
            }

            if ($document && !$customer) {
                $customer = $document->customer();
            }

            $paymentItems[] = new PaymentFormItem(
                amount: new Money($currency, $amount),
                document: $document,
            );
        }

        if (!$customer) {
            throw new FormException('Missing customer');
        }

        return new PaymentForm(
            company: $company,
            customer: $customer,
            totalAmount: Money::zero($currency), // The total amount is not used to create the charge and therefore can be ignored.
            paymentItems: $paymentItems,
        );
    }

    /**
     * Builds a charge object from a PayPal IPN event.
     */
    private function buildCharge(array $event, Customer $customer, string $description): ChargeValueObject
    {
        $currency = $event['mc_currency'];
        $amount = abs($event['mc_gross']);
        $amount = Money::fromDecimal($currency, $amount);

        return new ChargeValueObject(
            customer: $customer,
            amount: $amount, // We are intentionally treating pending as successful
            gateway: PaymentGatewayMetadata::PAYPAL,
            gatewayId: $event['txn_id'],
            method: PaymentMethod::PAYPAL,
            status: Transaction::STATUS_SUCCEEDED,
            merchantAccount: null,
            source: null,
            description: $description,
            timestamp: strtotime($event['payment_date']),
        );
    }
}
