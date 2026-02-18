<?php

namespace App\Tests\Statements\Libs;

use App\Statements\Enums\StatementType;
use App\Statements\Libs\BalanceForwardStatement;
use App\Statements\Libs\OpenItemStatement;
use App\Statements\Libs\StatementBuilder;
use App\Tests\AppTestCase;

class StatementBuilderTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
    }

    private function getBuilder(): StatementBuilder
    {
        return self::getService('test.statement_builder');
    }

    public function testBuildBalanceForward(): void
    {
        $statement = $this->getBuilder()->build(self::$customer, StatementType::BalanceForward);
        $this->assertInstanceOf(BalanceForwardStatement::class, $statement);
    }

    public function testBuildOpenItem(): void
    {
        $statement = $this->getBuilder()->build(self::$customer, StatementType::OpenItem);
        $this->assertInstanceOf(OpenItemStatement::class, $statement);
    }
}
