<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class LedgerSchema extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Currencies')
            ->addColumn('code', 'char', ['length' => 3])
            ->addColumn('numeric_code', 'integer')
            ->addColumn('num_decimals', 'smallinteger')
            ->addIndex('code', ['unique' => true])
            ->addIndex('numeric_code', ['unique' => true])
            ->create();

        $this->table('DocumentTypes')
            ->addColumn('name', 'string', ['length' => 255])
            ->addIndex('name', ['unique' => true])
            ->create();

        $this->table('ExchangeRates')
            ->addColumn('base_currency_id', 'integer')
            ->addColumn('target_currency_id', 'integer')
            ->addColumn('date', 'date')
            ->addColumn('exchange_rate', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addForeignKey('base_currency_id', 'Currencies', 'id')
            ->addForeignKey('target_currency_id', 'Currencies', 'id')
            ->addIndex(['base_currency_id', 'target_currency_id', 'date'], ['unique' => true])
            ->create();

        $this->table('Ledgers')
            ->addColumn('name', 'string')
            ->addIndex('name', ['unique' => true])
            ->addColumn('base_currency_id', 'integer')
            ->addColumn('created_at', 'timestamp')
            ->addForeignKey('base_currency_id', 'Currencies', 'id')
            ->create();

        $this->table('Accounts', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('name', 'string')
            ->addColumn('account_type', 'smallinteger')
            ->addColumn('ledger_id', 'integer')
            ->addColumn('currency_id', 'integer')
            ->addColumn('created_at', 'timestamp')
            ->addForeignKey('currency_id', 'Currencies', 'id')
            ->addForeignKey('ledger_id', 'Ledgers', 'id')
            ->addIndex(['ledger_id', 'name'], ['unique' => true])
            ->create();

        $this->table('Documents', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('reference', 'string')
            ->addColumn('document_type_id', 'integer')
            ->addColumn('ledger_id', 'integer')
            ->addColumn('date', 'date')
            ->addColumn('due_date', 'date', ['null' => true, 'default' => null])
            ->addColumn('number', 'string', ['length' => 32, 'null' => true, 'default' => null])
            ->addColumn('party_id', 'integer')
            ->addColumn('party_type', 'smallinteger')
            ->addColumn('created_at', 'timestamp')
            ->addIndex(['ledger_id', 'document_type_id', 'reference'], ['unique' => true])
            ->addForeignKey('document_type_id', 'DocumentTypes', 'id')
            ->addForeignKey('ledger_id', 'Ledgers', 'id')
            ->create();

        $this->table('LedgerTransactions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('transaction_date', 'date')
            ->addColumn('description', 'string')
            ->addColumn('document_id', 'biginteger', ['signed' => false])
            ->addColumn('currency_id', 'integer')
            ->addColumn('original_transaction_id', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('created_at', 'timestamp')
            ->addForeignKey('currency_id', 'Currencies', 'id')
            ->addForeignKey('document_id', 'Documents', 'id')
            ->addForeignKey('original_transaction_id', 'LedgerTransactions', 'id')
            ->create();

        $this->table('LedgerEntries', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('transaction_id', 'biginteger', ['signed' => false])
            ->addColumn('account_id', 'biginteger', ['signed' => false])
            ->addColumn('entry_type', 'enum', ['values' => ['D', 'C']])
            ->addColumn('amount', 'biginteger', ['signed' => false])
            ->addColumn('document_id', 'biginteger', ['signed' => false])
            ->addColumn('party_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('party_type', 'smallinteger', ['null' => true, 'default' => null])
            ->addColumn('amount_in_currency', 'biginteger', ['signed' => false])
            ->addForeignKey('account_id', 'Accounts', 'id')
            ->addForeignKey('document_id', 'Documents', 'id')
            ->addForeignKey('transaction_id', 'LedgerTransactions', 'id')
            ->create();
    }
}
