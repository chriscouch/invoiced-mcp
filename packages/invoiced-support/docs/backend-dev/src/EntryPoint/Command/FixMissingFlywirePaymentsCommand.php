<?php

namespace App\EntryPoint\Command;

use App\CashApplication\Models\Payment;
use App\Core\Multitenant\TenantContext;
use App\CustomerPortal\Exceptions\PaymentLinkException;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Operations\PaymentFlowReconcile;
use App\PaymentProcessing\ValueObjects\PaymentFlowReconcileData;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixMissingFlywirePaymentsCommand extends Command
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly PaymentFlowReconcile $paymentFlowReconcile,
        private readonly FlywirePrivateClient $client,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('fix:missing_flywire_payments')
            ->setDescription('Fixes missing Adyen Payments.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var FlywirePayment[] $flywirePayments */
        $flywirePayments = FlywirePayment::queryWithoutMultitenancyUnsafe()
            ->join(Payment::class, 'ar_payment_id', 'id', 'LEFT JOIN')
            ->where('status', [3, 4])
            ->where('Payments.id', null)
            ->all();

        foreach ($flywirePayments as $fp) {
            $this->tenant->set($fp->tenant());

            $data = $this->client->getPayment($fp->payment_id);

            $output->writeln('Payment reconciliation dispatched for: '.$fp->id);

            $flow = null;
            foreach ($data['recipient']['fields'] ?? [] as $field) {
                if ($field['id'] === 'invoiced_ref') {
                    /** @var ?PaymentFlow $flow */
                    $flow = PaymentFlow::where('identifier', $field['value'] ?? null)
                        ->oneOrNull();

                    break;
                }
            }


            if (!$flow) {
                $output->writeln("Payment flow not found for reference: {$fp->id} - ".json_encode($data['recipient'] ?? []));

                continue;
            }

            $flow->gateway = FlywireGateway::ID;
            $flow->merchant_account = $fp->merchant_account;
            $flow->save();

            $data = PaymentFlowReconcileData::fromFlywire($data);

            try {
                $this->paymentFlowReconcile->doReconcile($flow, $data);
                $fp->reference = $flow->identifier;
                $fp->save();
            } catch (FormException|PaymentLinkException|ChargeException $e) {
                $output->writeln($e->getMessage());
            }
        }

        $this->tenant->clear();

        return 0;
    }
}
