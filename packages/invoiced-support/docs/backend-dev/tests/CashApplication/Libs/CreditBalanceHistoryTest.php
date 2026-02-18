<?php

namespace App\Tests\CashApplication\Libs;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Libs\CreditBalanceHistory;
use App\CashApplication\Models\CreditBalance;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\Tests\AppTestCase;

class CreditBalanceHistoryTest extends AppTestCase
{
    public function testConstruct(): void
    {
        $customer = new Customer();
        $timestamp = time();
        $prevBalance = new Money('usd', 0);
        $transactions = [1 => new Transaction(['currency' => 'usd'])];
        $balance = new CreditBalance(['transaction_id' => 1]);
        $balance->balance = 0;
        $balances = [$balance];

        $history = new CreditBalanceHistory($customer, 'usd', $timestamp, $transactions, $balances, $prevBalance);

        $this->assertEquals($customer, $history->getCustomer());
        $this->assertEquals($timestamp, $history->getStartTimestamp());
        $this->assertEquals($transactions, $history->getTransactions());
        $this->assertEquals($balances, $history->getBalances());
        $this->assertEquals($prevBalance, $history->getPreviousBalance());
    }

    public function testBackfillMissingBalances(): void
    {
        $customer = new Customer(['id' => 10]);
        $timestamp = time();
        $prevBalance = new Money('usd', 10000);

        $a = new Transaction();
        $a->currency = 'usd';
        $a->amount = -60;
        $b = new Transaction();
        $b->currency = 'usd';
        $b->amount = 50;
        $c = new Transaction();
        $c->currency = 'usd';
        $c->amount = -40;
        $d = new Transaction();
        $d->currency = 'usd';
        $d->amount = 30;
        $transactions = [1 => $a, 2 => $b, 3 => $c, 4 => $d];

        $balance = new CreditBalance(['transaction_id' => 5]);
        $balances = [$balance];

        $history = new CreditBalanceHistory($customer, 'usd', $timestamp, $transactions, $balances, $prevBalance);

        // verify results
        $balances = $history->getBalances();
        $this->assertCount(4, $balances);
        $this->assertFalse($balances[0]->id());
        $this->assertEquals(1, $balances[0]->transaction_id);
        $this->assertFalse($balances[1]->id());
        $this->assertEquals(2, $balances[1]->transaction_id);
        $this->assertFalse($balances[2]->id());
        $this->assertEquals(3, $balances[2]->transaction_id);
        $this->assertFalse($balances[3]->id());
        $this->assertEquals(4, $balances[3]->transaction_id);
        $this->assertEquals(160, $balances[0]->balance);
        $this->assertEquals(110, $balances[1]->balance);
        $this->assertEquals(150, $balances[2]->balance);
        $this->assertEquals(120, $balances[3]->balance);

        foreach ($balances as $balance) {
            $this->assertEquals(10, $balance->customer_id);
        }
    }

    public function testSort(): void
    {
        // -1 => A should be before B
        //  1 => B should be before A
        //  0 => A and B are equal

        $a = new CreditBalance();
        $b = new CreditBalance();

        $a->timestamp = 0;
        $b->timestamp = 1;
        $this->assertEquals(-1, CreditBalanceHistory::sort($a, $b));

        $b->timestamp = 0;
        $a->timestamp = 1;
        $this->assertEquals(1, CreditBalanceHistory::sort($a, $b));

        $a = new CreditBalance(['transaction_id' => 2]);
        $b = new CreditBalance(['transaction_id' => 1]);
        $this->assertEquals(1, CreditBalanceHistory::sort($a, $b));

        $a = new CreditBalance(['transaction_id' => 1]);
        $b = new CreditBalance(['transaction_id' => 2]);
        $this->assertEquals(-1, CreditBalanceHistory::sort($a, $b));

        $a = new CreditBalance(['transaction_id' => 1]);
        $b = new CreditBalance(['transaction_id' => -1]);
        $this->assertEquals(-1, CreditBalanceHistory::sort($a, $b));

        $a = new CreditBalance(['transaction_id' => -1]);
        $b = new CreditBalance(['transaction_id' => 1]);
        $this->assertEquals(1, CreditBalanceHistory::sort($a, $b));

        $a = new CreditBalance(['transaction_id' => 2]);
        $b = new CreditBalance(['transaction_id' => 2]);
        $this->assertEquals(0, CreditBalanceHistory::sort($a, $b));
    }

    public function testAddTransaction(): void
    {
        $history = $this->buildHistory();

        $txn = new Transaction();
        $txn->date = 0;
        $txn->currency = 'usd';
        $txn->amount = 25;

        // add +25 at t=0
        $this->assertEquals($history, $history->addTransaction($txn));

        // verify results
        $balances = $history->getBalances();
        $this->assertCount(7, $balances);
        $this->assertEquals(1, $balances[0]->id());
        $this->assertEquals(2, $balances[1]->id());
        $this->assertEquals(3, $balances[2]->id());
        $this->assertFalse($balances[3]->id());
        $this->assertEquals(-1, $balances[3]->transaction_id);
        $this->assertEquals(4, $balances[4]->id());
        $this->assertEquals(5, $balances[5]->id());
        $this->assertEquals(6, $balances[6]->id());
        $this->assertEquals(60, $balances[0]->balance);
        $this->assertEquals(10, $balances[1]->balance);
        $this->assertEquals(50, $balances[2]->balance);
        $this->assertEquals(25, $balances[3]->balance);
        $this->assertEquals(-5, $balances[4]->balance);
        $this->assertEquals(15, $balances[5]->balance);
        $this->assertEquals(5, $balances[6]->balance);
    }

    public function testChangeTransactionDate(): void
    {
        $history = $this->buildHistory();

        // E.date -> 0
        $this->assertEquals($history, $history->changeTransaction(5, 0, -20));

        // verify results
        $balances = $history->getBalances();
        $this->assertCount(6, $balances);
        $this->assertEquals(1, $balances[0]->id());
        $this->assertEquals(2, $balances[1]->id());
        $this->assertEquals(3, $balances[2]->id());
        $this->assertEquals(5, $balances[3]->id());
        $this->assertEquals(4, $balances[4]->id());
        $this->assertEquals(6, $balances[5]->id());
        $this->assertEquals(60, $balances[0]->balance);
        $this->assertEquals(10, $balances[1]->balance);
        $this->assertEquals(50, $balances[2]->balance);
        $this->assertEquals(70, $balances[3]->balance);
        $this->assertEquals(40, $balances[4]->balance);
        $this->assertEquals(30, $balances[5]->balance);
    }

    public function testChangeTransactionAmount(): void
    {
        $history = $this->buildHistory();

        // F.amount -> 15
        $this->assertEquals($history, $history->changeTransaction(6, 1, 15));

        // verify results
        $balances = $history->getBalances();
        $this->assertCount(6, $balances);
        $this->assertEquals(1, $balances[0]->id());
        $this->assertEquals(2, $balances[1]->id());
        $this->assertEquals(3, $balances[2]->id());
        $this->assertEquals(4, $balances[3]->id());
        $this->assertEquals(5, $balances[4]->id());
        $this->assertEquals(6, $balances[5]->id());
        $this->assertEquals(60, $balances[0]->balance);
        $this->assertEquals(10, $balances[1]->balance);
        $this->assertEquals(50, $balances[2]->balance);
        $this->assertEquals(20, $balances[3]->balance);
        $this->assertEquals(40, $balances[4]->balance);
        $this->assertEquals(25, $balances[5]->balance);
    }

    public function testDeleteTransaction(): void
    {
        $history = $this->buildHistory();

        // delete C
        $this->assertEquals($history, $history->deleteTransaction(3));

        $balances = $history->getBalances();
        $this->assertCount(5, $balances);
        $this->assertEquals(1, $balances[0]->id());
        $this->assertEquals(2, $balances[1]->id());
        $this->assertEquals(4, $balances[2]->id());
        $this->assertEquals(5, $balances[3]->id());
        $this->assertEquals(6, $balances[4]->id());
        $this->assertEquals(60, $balances[0]->balance);
        $this->assertEquals(10, $balances[1]->balance);
        $this->assertEquals(-20, $balances[2]->balance);
        $this->assertEquals(0, $balances[3]->balance);
        $this->assertEquals(-10, $balances[4]->balance);
    }

    public function testSetUnsavedId(): void
    {
        $history = $this->buildHistory();

        $txn = new Transaction();
        $txn->date = 0;
        $txn->currency = 'usd';
        $txn->amount = 25;

        // add +25 at t=0
        $this->assertEquals($history, $history->addTransaction($txn));

        // set the ID
        $this->assertEquals($history, $history->setUnsavedId(7));

        // verify results
        $balances = $history->getBalances();
        $this->assertCount(7, $balances);
        $this->assertEquals(1, $balances[0]->id());
        $this->assertEquals(2, $balances[1]->id());
        $this->assertEquals(3, $balances[2]->id());
        $this->assertEquals(7, $balances[3]->transaction_id);
        $this->assertFalse($balances[3]->id());
        $this->assertEquals(4, $balances[4]->id());
        $this->assertEquals(5, $balances[5]->id());
        $this->assertEquals(6, $balances[6]->id());
    }

    public function testGetOverspend(): void
    {
        $history = $this->buildHistory();

        $this->assertNull($history->getOverspend());

        $history->deleteTransaction(1);

        /** @var CreditBalance $overspend */
        $overspend = $history->getOverspend();
        $this->assertInstanceOf(CreditBalance::class, $overspend);
        $this->assertEquals(2, $overspend->id());
        $this->assertEquals(0, $overspend->timestamp);
        $this->assertEquals(-50, $overspend->balance);
    }

    private function buildHistory(): CreditBalanceHistory
    {
        $customer = new Customer();
        $timestamp = time();
        $prevBalance = new Money('usd', 0);

        // transactions
        $tA = new Transaction();
        $tA->date = 0;
        $tA->currency = 'usd';
        $tA->amount = -60;
        $tB = new Transaction();
        $tB->date = 0;
        $tB->currency = 'usd';
        $tB->amount = 50;
        $tC = new Transaction();
        $tC->date = 0;
        $tC->currency = 'usd';
        $tC->amount = -40;
        $tD = new Transaction();
        $tD->date = 1;
        $tD->currency = 'usd';
        $tD->amount = 30;
        $tE = new Transaction();
        $tE->date = 1;
        $tE->currency = 'usd';
        $tE->amount = -20;
        $tF = new Transaction();
        $tF->date = 1;
        $tF->currency = 'usd';
        $tF->amount = 10;
        $transactions = [
            1 => $tA,
            2 => $tB,
            3 => $tC,
            4 => $tD,
            5 => $tE,
            6 => $tF,
        ];

        // credit balances
        $a = new CreditBalance(['transaction_id' => 1]);
        $b = new CreditBalance(['transaction_id' => 2]);
        $c = new CreditBalance(['transaction_id' => 3]);
        $d = new CreditBalance(['transaction_id' => 4]);
        $e = new CreditBalance(['transaction_id' => 5]);
        $f = new CreditBalance(['transaction_id' => 6]);
        $balances = [$b, $d, $f, $a, $e, $c];

        return new CreditBalanceHistory($customer, 'usd', $timestamp, $transactions, $balances, $prevBalance);
    }
}
