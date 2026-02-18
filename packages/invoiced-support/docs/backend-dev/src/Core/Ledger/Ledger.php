<?php

namespace App\Core\Ledger;

use App\Core\Ledger\Enums\DocumentType;
use App\Core\Ledger\Enums\EntryType;
use App\Core\Ledger\Exception\LedgerException;
use App\Core\Ledger\Repository\ChartOfAccounts;
use App\Core\Ledger\Repository\CurrencyRepository;
use App\Core\Ledger\Repository\DocumentRepository;
use App\Core\Ledger\Repository\DocumentTypeRepository;
use App\Core\Ledger\ValueObjects\Document;
use App\Core\Ledger\ValueObjects\Transaction;
use App\PaymentProcessing\Enums\MerchantAccountLedgerAccounts;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Exchanger\CurrencyPair;
use Exchanger\Exchanger;

final class Ledger
{
    const string ROUNDING_ACCOUNT = 'Rounding Difference';

    public readonly DocumentRepository $documents;
    public readonly ChartOfAccounts $chartOfAccounts;
    public readonly LedgerReporting $reporting;
    public readonly CurrencyRepository $currencyRepository;
    private readonly DocumentTypeRepository $documentTypeRepository;

    public function __construct(
        private readonly Connection $database,
        public readonly int $id,
        public readonly string $baseCurrency,
        ?CurrencyRepository $currencyRepository = null
    ) {
        $this->currencyRepository = $currencyRepository ?? new CurrencyRepository($this->database);
        $this->chartOfAccounts = new ChartOfAccounts($this->database, $this->id, $this->currencyRepository);
        $this->documents = new DocumentRepository($this->database, $this->id);
        $this->documentTypeRepository = new DocumentTypeRepository($this->database);
        $this->reporting = new LedgerReporting($this->database, $this);
    }

    public function convertCurrency(Exchanger $exchanger, string $transactionCurrency, CarbonImmutable $date, int $amount): int
    {
        $transactionCurrency = strtoupper($transactionCurrency);
        $pair = new CurrencyPair($transactionCurrency, $this->baseCurrency);
        $exchangeRate = $this->currencyRepository->getExchangeRate($exchanger, $pair, $date);

        return (int) round($amount * $exchangeRate->getValue());
    }

    /**
     * Syncs a document's desired state and transactions with the ledger.
     * This function is expensive and should only be used if the document
     * is unknown.
     *
     * @param Transaction[] $transactions
     *
     * @throws LedgerException
     */
    public function syncDocument(Document $document, array $transactions): void
    {
        $this->database->transactional(function () use ($document, $transactions) {
            // Create or update the document
            $documentId = $this->documents->getIdForDocument($document);
            if ($documentId) {
                $isExisting = true;
                $this->documents->update($documentId, $document);
            } else {
                $isExisting = false;
                $documentId = $this->documents->create($document);
            }

            // Get all IDs of all un-reversed transactions belonging to the document
            $transactionIds = [];
            if ($isExisting) {
                $transactionIds = $this->getTransactionIdsForDocument($documentId);
            }

            // Post each transaction that does not exist yet
            foreach ($transactions as $transaction) {
                // Check if it exists
                $found = false;
                foreach ($transactionIds as $k => $id) {
                    if ($this->transactionMatchesDb($documentId, $transaction, $id)) {
                        $found = true;
                        unset($transactionIds[$k]);
                        break;
                    }
                }

                // Create the transaction
                if (!$found) {
                    $this->createTransaction($documentId, $transaction);
                }
            }

            // Void any remaining transactions that were not included in the transaction list
            foreach ($transactionIds as $id) {
                $this->voidTransaction($id);
            }
        });
    }

    /**
     * For a given document this gets the list of transactions
     * that have not been reversed.
     */
    private function getTransactionIdsForDocument(int $documentId): array
    {
        // Start with the list of all original transactions
        $ids = $this->database->fetchFirstColumn("SELECT id FROM LedgerTransactions WHERE document_id=$documentId AND original_transaction_id IS NULL");
        $idChains = [];
        foreach ($ids as $id) {
            $idChains[] = [$id];
        }

        // Get all reversals
        $reversals = $this->database->fetchAllAssociative("SELECT id,original_transaction_id FROM LedgerTransactions WHERE document_id=$documentId AND original_transaction_id IS NOT NULL ORDER BY id");

        // Match each reversal to its original transaction.
        foreach ($reversals as $reversal) {
            // We must look through every root transaction to find
            // the transaction that this reverses.
            foreach ($idChains as &$idChain) {
                if (in_array($reversal['original_transaction_id'], $idChain)) {
                    $idChain[] = $reversal['id'];
                    break;
                }
            }
        }

        // Now we can build the list of un-reversed transactions.
        // This would be transactions that have never been reversed
        // or that have an even number of reversals, e.g. the original
        // transaction was reversed and then the reversal was reversed.
        $result = [];
        foreach ($idChains as $idChain) {
            // When the chain has an odd number of elements then
            // we consider the last item as un-reversed
            if (1 == count($idChain) % 2) { /* @phpstan-ignore-line */
                $result[] = end($idChain);
            }
        }

        return $result;
    }

    /**
     * Check if the ledger entries are the same by
     * computing the hash of each ledger entry in the
     * database and in the local version. If there is
     * any difference then we have a different transaction.
     */
    private function transactionMatchesDb(int $documentId, Transaction $transaction, int $transactionId): bool
    {
        // Compare transaction date and currency
        /** @var array $transactionDb */
        $transactionDb = $this->database->fetchAssociative('SELECT transaction_date,document_id,currency_id FROM LedgerTransactions WHERE id='.$transactionId);
        if ($transactionDb['transaction_date'] != $transaction->date->toDateString() || $transactionDb['currency_id'] != $this->currencyRepository->getId($transaction->currency)) {
            return false;
        }

        // Compare ledger entries
        $ledgerEntries = $this->database->fetchAllAssociative('SELECT account_id,entry_type,amount,amount_in_currency,party_type,party_id,document_id FROM LedgerEntries WHERE transaction_id='.$transactionId);
        $hashedDbEntries = [];
        foreach ($ledgerEntries as $row) {
            $hashedDbEntries[] = $row['account_id'].
                $row['entry_type'].
                $row['amount'].
                $row['amount_in_currency'].
                $row['party_type'].
                $row['party_id'].
                $row['document_id'];
        }
        sort($hashedDbEntries);

        $hashedLocalEntries = [];
        foreach ($transaction->entries as $ledgerEntry) {
            $hashedLocalEntries[] = $this->chartOfAccounts->getId($ledgerEntry->account).
                $ledgerEntry->amount->type->value.
                $ledgerEntry->amount->amount.
                $ledgerEntry->amount->amountInCurrency.
                $ledgerEntry->party?->type->value.
                $ledgerEntry->party?->id.
                ($ledgerEntry->documentId ?? $documentId);
        }
        sort($hashedLocalEntries);

        return $hashedDbEntries == $hashedLocalEntries;
    }

    public function getTransactions(int $page, int $limit = 10, array $filters = []): array
    {
        $types = $this->documentTypeRepository->getDocumentTypes();
        $types = array_flip($types);

        $qb = $this->database->createQueryBuilder();
        $qb->select('lt.id, lt.description, lt.transaction_date, lt.currency_id, lt.original_transaction_id, d.reference, d.document_type_id, d.date, d.party_id')
            ->from('LedgerTransactions', 'lt')
            ->leftJoin('lt', 'Documents', 'd', 'd.id = lt.document_id')
            ->andWhere('d.ledger_id = :ledger_id')
            ->addOrderBy('lt.transaction_date', 'DESC')
            ->addOrderBy('lt.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult(($page - 1) * $limit)
            ->setParameter('ledger_id', $this->id);

        if (isset($filters['date'])) {
            $qb->andWhere('lt.transaction_date BETWEEN :start AND :end')
                ->setParameter('start', $filters['date']['start'])
                ->setParameter('end', $filters['date']['end']);
        }

        $transactions = $qb->executeQuery()->fetchAllAssociativeIndexed();

        $qb = $this->database->createQueryBuilder();
        $transactionEntries = $qb->select('account_id,entry_type,amount,amount_in_currency,transaction_id')
            ->from('LedgerEntries')
            ->andWhere($qb->expr()->in('transaction_id', ':transaction_ids'))
            ->setParameter('transaction_ids', array_keys($transactions), ArrayParameterType::INTEGER)
            ->executeQuery()->fetchAllAssociative();

        foreach ($transactionEntries as $transactionEntry) {
            $key = $transactionEntry['transaction_id'];
            $transactions[$key]['document_type'] = $types[$transactions[$key]['document_type_id']];
            if (!isset($transactions[$key]['entries'])) {
                $transactions[$key]['entries'] = [];
            }
            $transactions[$key]['entries'][] = [
                'account_id' => $transactionEntry['account_id'],
                'entry_type' => $transactionEntry['entry_type'],
                'amount' => $transactionEntry['amount'],
                'amount_in_currency' => $transactionEntry['amount_in_currency'],
            ];
        }

        return $transactions;
    }

    private function voidDocumentById(int $documentId): void
    {
        // Void any transactions belonging to the document that have not already been voided
        $this->database->transactional(function () use ($documentId) {
            foreach ($this->getTransactionIdsForDocument($documentId) as $transactionId) {
                $this->voidTransaction($transactionId);
            }
        });
    }

    /**
     * Voids all transactions in the ledger that are associated with the given document.
     */
    public function voidDocument(Document $document): void
    {
        if ($documentId = $this->documents->getIdForDocument($document)) {
            $this->voidDocumentById($documentId);
        }
    }

    /**
     * Voids all documents that exist in the database but not in the list
     * of references. This is used to provide synchronization where a document
     * may have been deleted after it was added to the ledger.
     *
     * This function is expensive.
     *
     * @throws LedgerException
     */
    public function voidRemainingDocuments(DocumentType $type, array $references): void
    {
        $id = 0;
        $documentIds = [];
        while (!$id || count($documentIds)) {
            $documentIds = $this->database->fetchAllAssociative('SELECT id,reference FROM Documents WHERE ledger_id=? AND document_type_id=? AND id > ? ORDER BY id LIMIT 1000', [
                $this->id,
                $this->documents->getDocumentTypeId($type),
                $id,
            ]);
            $id = true; // ensure the loop exits if there are no results
            foreach ($documentIds as $row) {
                if (!in_array($row['reference'], $references)) {
                    $this->voidDocumentById($row['id']);
                }
                $id = $row['id'];
            }
        }
    }

    /**
     * Creates a new transaction in the ledger.
     */
    public function createTransaction(int $documentId, Transaction $transaction): void
    {
        // Validate ledger entries
        $net = 0;
        $netInCurrency = 0;
        foreach ($transaction->entries as $ledgerEntry) {
            if ($ledgerEntry->amount->amount < 0 || $ledgerEntry->amount->amountInCurrency < 0) {
                throw new LedgerException('Ledger entry amount cannot be negative');
            }

            if (!$ledgerEntry->amount->amount || !$ledgerEntry->amount->amountInCurrency) {
                throw new LedgerException('Ledger entry amount cannot be zero');
            }

            $net += EntryType::DEBIT == $ledgerEntry->amount->type ? $ledgerEntry->amount->amount : -$ledgerEntry->amount->amount;
            $netInCurrency += EntryType::DEBIT == $ledgerEntry->amount->type ? $ledgerEntry->amount->amountInCurrency : -$ledgerEntry->amount->amountInCurrency;
        }
        $net = round($net);
        if (round($netInCurrency)) {
            throw new LedgerException('Unbalanced journal entry in transaction currency: '.$netInCurrency);
        }

        $this->database->transactional(function () use ($documentId, $transaction, $net) {
            // Create a transaction
            $this->database->insert('LedgerTransactions', [
                'document_id' => $documentId,
                'description' => $transaction->description,
                'transaction_date' => $transaction->date->toDateString(),
                'currency_id' => $this->currencyRepository->getId($transaction->currency),
                'created_at' => CarbonImmutable::now()->toDateTimeString(),
            ]);
            $transactionId = $this->database->lastInsertId();

            // Create ledger entries
            foreach ($transaction->entries as $ledgerEntry) {
                $this->database->insert('LedgerEntries', [
                    'transaction_id' => $transactionId,
                    'account_id' => $this->chartOfAccounts->getId($ledgerEntry->account),
                    'entry_type' => $ledgerEntry->amount->type->value,
                    'party_type' => $ledgerEntry->party?->type->value,
                    'party_id' => $ledgerEntry->party?->id,
                    'document_id' => $ledgerEntry->documentId ?? $documentId,
                    'amount' => $ledgerEntry->amount->amount,
                    'amount_in_currency' => $ledgerEntry->amount->amountInCurrency,
                ]);
            }

            if ($net) {
                try {
                    $this->database->insert('LedgerEntries', [
                        'transaction_id' => $transactionId,
                        'account_id' => $this->chartOfAccounts->getId(self::ROUNDING_ACCOUNT),
                        'entry_type' => ($net < 0 ? EntryType::DEBIT : EntryType::CREDIT)->value,
                        'document_id' => $documentId,
                        'amount' => abs($net),
                    ]);
                } catch (LedgerException) {
                    throw new LedgerException('Unbalanced journal entry: '.$net);
                }
            }
        });
    }

    /**
     * Voids a transaction by reversing all of its ledger entries.
     */
    private function voidTransaction(int $transactionId): void
    {
        /** @var array $originalTransaction */
        $originalTransaction = $this->database->fetchAssociative('SELECT * FROM LedgerTransactions WHERE id='.$transactionId);
        /** @var array $originalLedgerEntries */
        $originalLedgerEntries = $this->database->fetchAllAssociative('SELECT * FROM LedgerEntries WHERE transaction_id='.$transactionId);

        $this->database->transactional(function () use ($originalTransaction, $originalLedgerEntries) {
            // Create a reversing transaction
            $this->database->insert('LedgerTransactions', [
                'document_id' => $originalTransaction['document_id'],
                'description' => $originalTransaction['description'],
                'transaction_date' => $originalTransaction['transaction_date'],
                'currency_id' => $originalTransaction['currency_id'],
                'original_transaction_id' => $originalTransaction['id'],
                'created_at' => CarbonImmutable::now()->toDateTimeString(),
            ]);
            $reversalTransactionId = $this->database->lastInsertId();

            // Create reversing ledger entries
            foreach ($originalLedgerEntries as $ledgerEntry) {
                $this->database->insert('LedgerEntries', [
                    'transaction_id' => $reversalTransactionId,
                    'account_id' => $ledgerEntry['account_id'],
                    // Debits become credits and vice versa
                    'entry_type' => 'D' == $ledgerEntry['entry_type'] ? 'C' : 'D',
                    'party_type' => $ledgerEntry['party_type'],
                    'party_id' => $ledgerEntry['party_id'],
                    'document_id' => $ledgerEntry['document_id'],
                    'amount' => $ledgerEntry['amount'],
                    'amount_in_currency' => $ledgerEntry['amount_in_currency'],
                ]);
            }
        });
    }
}
