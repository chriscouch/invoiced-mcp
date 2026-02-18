<?php

namespace App\EntryPoint\CronJob;

use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\TenantContext;
use App\Core\Utils\InfuseUtility as Utility;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\ChargeApplicationType;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Interfaces\ChargeApplicationItemInterface;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\InitiatedCharge;
use App\PaymentProcessing\Models\InitiatedChargeDocument;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Operations\ProcessPayment;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use Throwable;

class ReplayInitiatedCharge implements CronJobInterface
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly ProcessPayment $processPayment
    ) {
    }

    public static function getName(): string
    {
        return 'payment_replay';
    }

    public static function getLockTtl(): int
    {
        return 900;
    }

    public function execute(Run $run): void
    {
        /** @var InitiatedCharge[] $charges */
        $charges = InitiatedCharge::queryWithoutMultitenancyUnsafe()
            ->where('charge', '{}', '!=')
            ->where('charge', '', '!=')
            ->where('created_at', Utility::unixToDb(time() - 600), '<=')
            ->all();

        foreach ($charges as $charge) {
            try {
                $this->executeCharge($charge);
            } catch (Throwable) {
            }
        }
        $this->tenant->clear();
        $run->writeOutput('Processed '.count($charges).' replays');
    }

    private function executeCharge(InitiatedCharge $charge): void
    {
        $tenant = $charge->tenant();
        $this->tenant->set($tenant);

        $chargeValueObject = $charge->getCharge();

        if (!$chargeValueObject->amount->isPositive()) {
            return;
        }
        /** @var InitiatedChargeDocument[] $documents */
        $documents = InitiatedChargeDocument::where('initiated_charge_id', $charge->id)
            ->execute();

        $merchantAccount = null;
        if ($charge->merchant_account_id) {
            $merchantAccount = MerchantAccount::find($charge->merchant_account_id);
        }

        if (null === $merchantAccount) {
            $merchantAccount = new MerchantAccount();
            $merchantAccount->gateway = $charge->gateway;
        }

        $method = PaymentMethod::instance($tenant, $chargeValueObject->method);

        $items = [];
        $docs = [];
        foreach ($documents as $document) {
            $item = $this->getApplicationItem($document, $charge);
            $items[] = $item;
            $docs[] = $item->getDocument();
        }

        $this->processPayment->setMutexLock($docs);
        $application = new ChargeApplication($items, PaymentFlowSource::from($charge->application_source));

        // additional check for integrity
        $money = Money::fromDecimal($charge->currency, $charge->amount);
        if (!$application->getPaymentAmount()->equals($money)) {
            return;
        }

        if (Charge::SUCCEEDED === $chargeValueObject->status || Charge::PENDING === $chargeValueObject->status) {
            $this->processPayment->handleSuccessfulPayment($chargeValueObject, $method, $application, $charge, null, null);

            return;
        }
        $e = new ChargeException('replay', $chargeValueObject);
        $this->processPayment->handleFailedPayment($e, $method, $application, $charge, null, null);
    }

    private function getApplicationItem(InitiatedChargeDocument $document, InitiatedCharge $charge): ChargeApplicationItemInterface
    {
        $type = ChargeApplicationType::from($document->document_type);
        $money = Money::fromDecimal($charge->currency, $document->amount);

        return $type->chargeApplicationItem($document, $money);
    }
}
