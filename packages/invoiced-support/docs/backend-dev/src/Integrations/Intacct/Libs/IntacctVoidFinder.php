<?php

namespace App\Integrations\Intacct\Libs;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Models\IntacctAccount;
use Carbon\CarbonImmutable;
use SimpleXMLElement;

/**
 * Class dedicated to finding a reversed Intacct payments matching record.
 * I.e. the payment which the reversal reversed.
 */
class IntacctVoidFinder
{
    private const FIELDS = [
        'RECORDNO',
        'CUSTOMERID',
        'STATE',
        'CURRENCY',
        'RECEIPTDATE',
        'DOCNUMBER',
        'RECORDID',
        'PAYMENTTYPE',
        'INVOICES',
        'CREDITS',
        'AUWHENCREATED',
    ];

    public function __construct(private IntacctApi $client)
    {
    }

    /**
     * Searches for an Intacct payment associated with the provided payment
     * reversal.
     */
    public function findMatch(SimpleXMLElement $payment, IntacctAccount $account): ?SimpleXMLElement
    {
        $this->client->setAccount($account);

        // query data
        $recordNum = (string) $payment->{'RECORDNO'};
        $auWhenCreated = new CarbonImmutable((string) $payment->{'AUWHENCREATED'});

        // AUWHENCREATED needs reformatting (Intacct returns in one format but only allows querying in another)
        $whenCreated = $auWhenCreated->format('m/d/Y H:i:s');
        $whenCreatedPlus1 = $auWhenCreated->addSecond()->format('m/d/Y H:i:s');

        // NOTE: AUWHENCREATED and WHENMODIFIED are date-times with granularity down to the second.
        // When Intacct reversals are created for a payment, that payment is modified at the exact
        // time the reversal is created. With...
        // - the time granularity
        // - voided state
        // - invoice application match
        // ...we can be fairly positive that any found match is the payment associated w/ the reversal.
        // However, to avoid unwanted voids, we should return null if more than one match is found.
        $queryCondition = "RECORDNO != \"$recordNum\" AND (WHENMODIFIED = \"$whenCreated\" OR WHENMODIFIED = \"$whenCreatedPlus1\")";

        // keep track of all matches found
        $matches = [];

        try {
            // Query for possible matches. For efficiency reasons we'll assume
            // that all matches are within the first 100 results. This is because
            // the likelihood of more than 100 payments being modified at the exact
            // same second in time is very low.
            $queryResult = $this->client->getPayments(['RECORDNO', 'STATE'], $queryCondition, 100);

            // iterate possible matches to find all matches
            foreach ($queryResult->getData() as $paymentRef) {
                if ('V' != (string) $paymentRef->{'STATE'}) {
                    // Non-voided payments cannot be matches to of reversals.
                    // STATE is not a query-able field.
                    continue;
                }

                $potentialMatch = $this->client->getPayment((string) $paymentRef->{'RECORDNO'}, self::FIELDS);
                if ($this->isMatch($payment, $potentialMatch)) {
                    $matches[] = $potentialMatch;
                }
            }
        } catch (IntegrationApiException) {
            return null;
        }

        // avoid unwanted voids
        if (1 == count($matches)) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Checks if two payments are similar in regards to:
     * - customer
     * - currency
     * - invoice applications.
     *
     * Invoice applications are compared by record id and absolute values
     * of their amounts. Absolute values are used because the goal is to find
     * a payment that belongs to a payment reversal. Reversals are negative.
     */
    private function isMatch(SimpleXMLElement $p1, SimpleXMLElement $p2): bool
    {
        // compare customers
        if ((string) $p1->{'CUSTOMERID'} != (string) $p2->{'CUSTOMERID'}) {
            return false;
        }

        // compare currencies
        $currency1 = (string) $p1->{'CURRENCY'};
        $currency2 = (string) $p2->{'CURRENCY'};
        if ($currency1 != $currency2) {
            return false;
        }

        // compare transactions
        $transactionKeys = ['INVOICES', 'CREDITS'];
        foreach ($transactionKeys as $key) {
            if (count($p1->{$key}) != count($p2->{$key})) {
                return false;
            }

            $i = 0;
            foreach ($p1->{$key} as $t1) {
                $t2 = $p2->{$key}[$i++];

                if ((string) $t1->{'RECORD'} != (string) $t2->{'RECORD'}) {
                    return false;
                }

                $amount1 = Money::fromDecimal($currency1, abs((float) $t1->{'APPLIEDAMOUNT'}));
                $amount2 = Money::fromDecimal($currency1, abs((float) $t2->{'APPLIEDAMOUNT'}));
                if (!$amount1->equals($amount2)) {
                    return false;
                }
            }
        }

        return true;
    }
}
