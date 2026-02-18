<?php

namespace App\Tests\CashApplication\Libs;

use App\CashApplication\ValueObjects\TransactionTree;
use App\CashApplication\Models\Transaction;
use App\Tests\AppTestCase;

class TransactionTreeTest extends AppTestCase
{
    private static TransactionTree $tree;
    private static Transaction $childPayment;
    private static Transaction $childRefund;
    private static Transaction $childCredit;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasTransaction();

        self::$childPayment = new Transaction();
        self::$childPayment->setCustomer(self::$customer);
        self::$childPayment->setParentTransaction(self::$transaction);
        self::$childPayment->amount = 100;
        self::$childPayment->save();

        self::$childRefund = new Transaction();
        self::$childRefund->type = Transaction::TYPE_REFUND;
        self::$childRefund->setCustomer(self::$customer);
        self::$childRefund->setParentTransaction(self::$transaction);
        self::$childRefund->amount = 50;
        self::$childRefund->save();

        self::$childCredit = new Transaction();
        self::$childCredit->type = Transaction::TYPE_ADJUSTMENT;
        self::$childCredit->setCustomer(self::$customer);
        self::$childCredit->setParentTransaction(self::$childRefund);
        self::$childCredit->amount = -75;
        self::$childCredit->save();
    }

    public function testGetRoot(): void
    {
        $txn = new Transaction();
        $tree = new TransactionTree($txn);
        $this->assertEquals($txn, $tree->getRoot());
    }

    public function testGetChildren(): void
    {
        $tree = $this->getTree();

        $children = $tree->getChildren();

        $this->assertCount(2, $children);

        $this->assertInstanceOf(TransactionTree::class, $children[0]);
        $root = $children[0]->getRoot();
        $this->assertInstanceOf(Transaction::class, $root);
        $this->assertEquals(self::$childPayment->id(), $root->id());

        $this->assertInstanceOf(TransactionTree::class, $children[1]);
        $root = $children[1]->getRoot();
        $this->assertInstanceOf(Transaction::class, $root);
        $this->assertEquals(self::$childRefund->id(), $root->id());
    }

    public function testToArray(): void
    {
        $tree = $this->getTree();

        $payment = self::$childPayment->toArray();
        $payment['children'] = [];

        $credit = self::$childCredit->toArray();
        $credit['children'] = [];
        $refund = self::$childRefund->toArray();
        $refund['children'] = [$credit];

        $expected = [
            $payment,
            $refund,
        ];

        $this->assertEquals($expected, $tree->toArray());
    }

    private function getTree(): TransactionTree
    {
        if (!isset(self::$tree)) {
            self::$tree = new TransactionTree(self::$transaction);
        }

        return self::$tree;
    }
}
