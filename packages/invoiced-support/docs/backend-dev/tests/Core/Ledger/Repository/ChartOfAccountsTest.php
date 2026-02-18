<?php

namespace App\Tests\Core\Ledger\Repository;

use App\Core\Ledger\Enums\AccountType;
use App\Core\Ledger\Exception\LedgerException;
use App\Core\Ledger\Repository\LedgerRepository;
use App\Tests\AppTestCase;

class ChartOfAccountsTest extends AppTestCase
{
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::getService('test.database')->executeQuery('SET foreign_key_checks = 0; DELETE FROM Ledgers WHERE name="Chart of Accounts Test"; SET foreign_key_checks = 1;');
    }

    private function getLedgerRepository(): LedgerRepository
    {
        return new LedgerRepository(self::getService('test.database'));
    }

    public function testGetIdNotFound(): void
    {
        $this->expectException(LedgerException::class);
        $this->expectExceptionMessage('Account does not exist: Not Found');

        $ledger = $this->getLedgerRepository()->findOrCreate('Chart of Accounts Test', 'USD');
        $ledger->chartOfAccounts->getId('Not Found');
    }

    public function testRepository(): void
    {
        $ledger = $this->getLedgerRepository()->findOrCreate('Chart of Accounts Test', 'USD');

        $accountId = $ledger->chartOfAccounts->findOrCreate('Accounts Receivable', AccountType::Asset, 'USD');
        $this->assertEquals($accountId, $ledger->chartOfAccounts->getId('Accounts Receivable'));
        $this->assertEquals($accountId, $ledger->chartOfAccounts->findOrCreate('Accounts Receivable', AccountType::Asset, 'USD'));

        $accountId2 = $ledger->chartOfAccounts->findOrCreate('Accounts Payable', AccountType::Liability, 'USD');
        $this->assertNotEquals($accountId, $accountId2);
    }
}
