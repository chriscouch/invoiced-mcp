<?php

namespace App\CashApplication\Libs;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Models\Payment;
use App\Core\Database\TransactionManager;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Queue\Queue;
use App\Core\Utils\RandomString;
use App\EntryPoint\QueueJob\CashApplicationMatchingJob;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class CashApplicationMatchmaker implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const INVOICE_THRESHOLD_FOR_ONE_CUSTOMER = 15;
    private const COMBINATION_THRESHOLD = 48000;

    public function __construct(
        private Connection $database,
        private TransactionManager $transactionManager,
        private Queue $queue
    ) {
    }

    public function shouldLookForMatches(Payment $payment): bool
    {
        if (!$payment->tenant()->features->has('cash_match')) {
            return false;
        }

        return !$payment->applied && !$payment->voided && !$payment->customer && null === $payment->matched;
    }

    public function enqueue(Payment $payment, bool $isEdit): void
    {
        $this->queue->enqueue(CashApplicationMatchingJob::class, [
            'payment' => $payment->id(),
            'isEdit' => $isEdit,
        ]);
    }

    public function run(Payment $payment, bool $isEdit = false): void
    {
        $invoiceBalanceThreshold = $this->calculateMaximumInvoiceAmount($payment);
        $this->logger->info('CASH MATCH - Max Inv Amount: '.$invoiceBalanceThreshold);

        if ($payment->customer) {
            $combinations = $this->getAllCombinationsForCustomer($payment, $invoiceBalanceThreshold);
        } else {
            $combinations = $this->getAllCombinations($payment, $invoiceBalanceThreshold);
        }

        [$matches, $shortPayMatches] = $this->findMatches($combinations, $payment);

        $this->sortMatches($matches, $shortPayMatches);

        $this->logger->info('CASH MATCH - Matches: '.print_r($matches, true));
        $this->logger->info('CASH MATCH - Short Pay Matches: '.print_r($shortPayMatches, true));

        $this->transactionManager->perform(function () use ($isEdit, $matches, $shortPayMatches, $payment) {
            $this->saveResult($isEdit, $matches, $shortPayMatches, $payment);
        });
    }

    private function calculateMaximumInvoiceAmount(Payment $payment): float
    {
        $company = $payment->tenant();
        if ('percent' == $company->cash_application_settings->short_pay_units) {
            return $payment->balance / (1 - ($company->cash_application_settings->short_pay_amount / 100));
        }
        $balance = Money::fromDecimal($payment->currency, $payment->balance);
        $shortPayAmount = Money::fromDecimal($payment->currency, $company->cash_application_settings->short_pay_amount);

        return $balance->add($shortPayAmount)->toDecimal();
    }

    private function deleteOldMatches(Payment $payment): void
    {
        $this->database->delete('InvoiceUnappliedPaymentAssociations', [
            '`payment_id`' => $payment->id(),
        ]);
    }

    private function getAllCombinationsForCustomer(Payment $payment, float $invoiceBalanceThreshold): array
    {
        $sql = 'SELECT `id`, `balance` as `amount`, `date` FROM Invoices i WHERE `balance`<=:balanceThreshold AND `closed`=false AND `voided`=false AND `draft`=false AND `paid`=false AND `payment_plan_id` IS NULL AND `autopay`=false AND `customer`=:customer AND `currency`=:currency AND `tenant_id`=:tenantId AND NOT EXISTS (SELECT * FROM InvoiceUnappliedPaymentAssociations WHERE `invoice_id`=i.`id` AND `primary`=true)';

        $result = $this->database->fetchAllAssociative($sql, ['balanceThreshold' => $invoiceBalanceThreshold, 'customer' => $payment->customer, 'currency' => $payment->currency, 'tenantId' => $payment->tenant_id]);

        $invoices = [];
        foreach ($result as $row) {
            $invoices[] = $row;
        }
        $this->logger->info('CASH MATCH - Invoices: '.print_r($invoices, true));

        return $this->findCombinations($invoices, self::INVOICE_THRESHOLD_FOR_ONE_CUSTOMER);
    }

    private function getAllCombinations(Payment $payment, float $invoiceBalanceThreshold): array
    {
        $sql = 'SELECT GROUP_CONCAT(id), GROUP_CONCAT(balance), GROUP_CONCAT(date) FROM Invoices i WHERE `balance`<=:balanceThreshold AND `closed`=false AND `voided`=false AND `draft`=false AND `paid`=false AND `payment_plan_id` IS NULL AND `autopay`=false AND `currency`=:currency AND `tenant_id`=:tenantId AND NOT EXISTS (SELECT * FROM InvoiceUnappliedPaymentAssociations WHERE `invoice_id`=i.`id` AND `primary`=true) GROUP BY `customer`';

        $result = $this->database->fetchAllAssociative($sql, ['balanceThreshold' => $invoiceBalanceThreshold, 'currency' => $payment->currency, 'tenantId' => $payment->tenant_id]);
        $invoiceGroups = [];

        foreach ($result as $row) {
            $ids = $row['GROUP_CONCAT(id)'];
            $balances = $row['GROUP_CONCAT(balance)'];
            $dates = $row['GROUP_CONCAT(date)'];

            $ids = explode(',', $ids);
            $balances = explode(',', $balances);
            $dates = explode(',', $dates);

            $invoiceGroups[] = array_map(fn ($id, $amount, $date) => [
                'id' => $id,
                'amount' => $amount,
                'date' => $date,
            ], $ids, $balances, $dates);
        }
        $this->logger->info('CASH MATCH - Groups: '.print_r($invoiceGroups, true));

        $combinations = [];
        $invoiceThreshold = $this->calculateInvoiceThreshold();
        $this->logger->info('CASH MATCH - Inv threshold: '.$invoiceThreshold);

        foreach ($invoiceGroups as $group) {
            $combinations = array_merge($combinations, $this->findCombinations($group, $invoiceThreshold));
        }

        return $combinations;
    }

    private function calculateInvoiceThreshold(): int
    {
        $customerCount = Customer::count();
        $this->logger->info('CASH MATCH - Customer Count: '.$customerCount);
        $combinationsPerCustomer = self::COMBINATION_THRESHOLD / $customerCount;
        $this->logger->info('CASH MATCH - Comb per cust: '.$combinationsPerCustomer);

        return (int) floor(log($combinationsPerCustomer, 2));
    }

    private function findCombinations(array $array, int $invoiceThreshold): array
    {
        if (count($array) <= $invoiceThreshold) {
            $this->logger->info('CASH MATCH - findAllCombination');

            return $this->findAllCombinations($array);
        }
        $this->logger->info('CASH MATCH - findSubsetCombinations');

        return $this->findSubsetCombinations($array, $invoiceThreshold);
    }

    /**
     * Source: https://www.oreilly.com/library/view/php-cookbook/1565926811/ch04s25.html.
     */
    private function findAllCombinations(array $array): array
    {
        // initialize by adding the empty set
        $results = [[]];

        foreach ($array as $element) {
            foreach ($results as $combination) {
                $results[] = [...[$element], ...$combination];
            }
        }

        // remove empty set
        array_splice($results, 0, 1);

        return $results;
    }

    private function findSubsetCombinations(array $array, int $invoiceThreshold): array
    {
        uasort($array, fn ($a, $b) => $a['date'] <=> $b['date']);

        $subArray = array_slice($array, 0, $invoiceThreshold);
        $this->logger->info('CASH MATCH - Subset array: '.print_r($subArray, true));

        $results = $this->findAllCombinations($subArray);

        $results[] = $array;
        $remainingElements = array_slice($array, $invoiceThreshold);

        foreach ($remainingElements as $element) {
            $results[] = [$element];
        }

        return $results;
    }

    private function findMatches(array $combinations, Payment $payment): array
    {
        $matches = [];
        $shortPayMatches = [];

        foreach ($combinations as &$combination) {
            $comboTotal = 0;
            $dateAvg = 0;
            foreach ($combination as $item) {
                $comboTotal += $item['amount'];
                $dateAvg += $item['date'];
            }

            $combination['dateAverage'] = $dateAvg / count($combination);
            $combination['total'] = $comboTotal;
            if ($combination['total'] == $payment->balance) {
                $matches[] = $combination;
            } elseif ($percentDiff = $this->getPercentDifference($combination['total'], $payment->balance)) {
                if ($this->isWithinShortPayThreshold($combination['total'], $payment, $percentDiff)) {
                    $combination['percentDifference'] = $percentDiff;
                    $shortPayMatches[] = $combination;
                }
            }
        }

        unset($combination);
        $this->logger->info('CASH MATCH - Combos: '.print_r($combinations, true));

        return [$matches, $shortPayMatches];
    }

    private function sortMatches(array &$matches, array &$shortPayMatches): void
    {
        uasort($matches, fn ($a, $b) => $a['dateAverage'] <=> $b['dateAverage']);

        uasort($shortPayMatches, function ($a, $b) {
            if ($a['percentDifference'] == $b['percentDifference']) {
                return $a['dateAverage'] <=> $b['dateAverage'];
            }

            return ($a['percentDifference'] < $b['percentDifference']) ? -1 : 1;
        });
    }

    private function getPercentDifference(float $groupTotal, float $paymentAmount): ?float
    {
        if ($paymentAmount > $groupTotal) {
            return null;
        }

        $difference = $groupTotal - $paymentAmount;
        $percentOff = $difference / $groupTotal;

        return $percentOff * 100;
    }

    private function isWithinShortPayThreshold(float $groupTotal, Payment $payment, float $percentDiff): bool
    {
        $company = $payment->tenant();
        if ('percent' == $company->cash_application_settings->short_pay_units) {
            return $percentDiff <= $company->cash_application_settings->short_pay_amount;
        } elseif ('dollars' == $company->cash_application_settings->short_pay_units) {
            return ($groupTotal - $payment->balance) <= $company->cash_application_settings->short_pay_amount;
        }

        return false;
    }

    private function saveResult(bool $isEdit, array $matches, array $shortPayMatches, Payment $payment): void
    {
        if ($isEdit) {
            $this->deleteOldMatches($payment);
        }

        $payment->matched = false;
        if (!empty($matches) || !empty($shortPayMatches)) {
            $payment->matched = true;
        }
        $payment->saveOrFail();

        $totalMatches = count($matches) + count($shortPayMatches);

        if (0 == $totalMatches) {
            return;
        }

        $certainty = 1 / $totalMatches * 100;

        if (!empty($matches)) {
            $primaryMatch = array_splice($matches, 0, 1);

            if (!empty($primaryMatch[0])) {
                $groupId = RandomString::generate(10, 'abcdefghijklmnopqrstuvwxyz1234567890');
                foreach ($primaryMatch[0] as $item) {
                    if (isset($item['id'])) {
                        $this->database->insert('InvoiceUnappliedPaymentAssociations', [
                            '`invoice_id`' => $item['id'],
                            '`payment_id`' => $payment->id(),
                            '`group_id`' => $groupId,
                            '`primary`' => 1,
                            '`certainty`' => $certainty,
                        ]);
                    }
                }
            }
        } elseif (!empty($shortPayMatches)) {
            $primaryMatch = array_splice($shortPayMatches, 0, 1);

            if (!empty($primaryMatch[0])) {
                $groupId = RandomString::generate(10, 'abcdefghijklmnopqrstuvwxyz1234567890');
                foreach ($primaryMatch[0] as $item) {
                    if (isset($item['id'])) {
                        $this->database->insert('InvoiceUnappliedPaymentAssociations', [
                            '`invoice_id`' => $item['id'],
                            '`payment_id`' => $payment->id(),
                            '`group_id`' => $groupId,
                            '`primary`' => 1,
                            '`short_pay`' => 1,
                            '`certainty`' => $certainty,
                        ]);
                    }
                }
            }
        }

        foreach ($matches as $match) {
            if (!empty($match)) {
                $groupId = RandomString::generate(10, 'abcdefghijklmnopqrstuvwxyz1234567890');
                foreach ($match as $item) {
                    if (isset($item['id'])) {
                        $this->database->insert('InvoiceUnappliedPaymentAssociations', [
                            '`invoice_id`' => $item['id'],
                            '`payment_id`' => $payment->id(),
                            '`group_id`' => $groupId,
                            '`certainty`' => $certainty,
                        ]);
                    }
                }
            }
        }

        foreach ($shortPayMatches as $match) {
            if (!empty($match)) {
                $groupId = RandomString::generate(10, 'abcdefghijklmnopqrstuvwxyz1234567890');
                foreach ($match as $item) {
                    if (isset($item['id'])) {
                        $this->database->insert('InvoiceUnappliedPaymentAssociations', [
                            '`invoice_id`' => $item['id'],
                            '`payment_id`' => $payment->id(),
                            '`group_id`' => $groupId,
                            '`short_pay`' => 1,
                            '`certainty`' => $certainty,
                        ]);
                    }
                }
            }
        }
    }
}
