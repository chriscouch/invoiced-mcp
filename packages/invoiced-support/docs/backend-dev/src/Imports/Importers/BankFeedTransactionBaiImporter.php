<?php

namespace App\Imports\Importers;

use App\CashApplication\Libs\BaiUtility;
use App\CashApplication\Models\BankFeedTransaction;
use App\CashApplication\Operations\CreateBankFeedTransaction;
use App\Core\Database\TransactionManager;
use App\Core\Orm\Exception\ModelException;
use App\Core\Utils\RandomString;
use App\Imports\Exceptions\RecordException;
use App\Imports\Exceptions\ValidationException;
use App\Imports\Models\Import;
use App\Imports\ValueObjects\ImportRecordResult;
use Carbon\CarbonImmutable;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use STS\Bai2\Bai2;
use STS\Bai2\Records\AccountRecord;
use STS\Bai2\Records\GroupRecord;
use STS\Bai2\Records\TransactionRecord;
use Throwable;

class BankFeedTransactionBaiImporter extends BaseFileImporter implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private CreateBankFeedTransaction $operation,
        TransactionManager $transactionManager
    ) {
        parent::__construct($transactionManager);
    }

    //
    // ImporterInterface
    //

    public function getName(string $type, array $options): string
    {
        return 'Bank Feed Transactions';
    }

    public function build(array $mapping, array $lines, array $options, Import $import): array
    {
        if (!isset($lines['bai_text'])) {
            throw new ValidationException('Could not find BAI file text to parse.');
        }

        $stream = fopen('php://memory', 'r+');
        if (!$stream) {
            throw new ValidationException('Could not create stream.');
        }

        fwrite($stream, $lines['bai_text']);
        rewind($stream);

        try {
            $fileRecord = Bai2::parseFromResource($stream);

            $transactions = [];
            /** @var GroupRecord $group */
            foreach ($fileRecord->getGroups() as $group) {
                /** @var AccountRecord $account */
                foreach ($group->getAccounts() as $account) {
                    /** @var TransactionRecord $transaction */
                    foreach ($account->getTransactions() as $transaction) {
                        $typeCodeDetail = BaiUtility::getTypeCode($transaction->getTypeCode());
                        if (!$typeCodeDetail) {
                            continue;
                        }

                        // Only Detail type transactions are considered.
                        // Summary and Status types are skipped.
                        if ('Detail' != $typeCodeDetail['level']) {
                            continue;
                        }

                        try {
                            $date = CarbonImmutable::createFromFormat('ymd', $group->getAsOfDate());
                            if (!$date) {
                                throw new ValidationException('Could not parse date: '.$group->getAsOfDate());
                            }
                        } catch (Throwable) {
                            throw new ValidationException('Could not parse date: '.$group->getAsOfDate());
                        }

                        $amount = $transaction->getAmount() / 100;
                        // Credits should be reflected as a negative amount in our system
                        if ('CR' == $typeCodeDetail['transaction']) {
                            $amount *= -1;
                        }

                        $transactionId = $transaction->getBankReferenceNumber();
                        // Often the bank reference number is not populated. In order
                        // to derive a unique transaction ID we will generate a random ID.
                        // This could yield duplicate transactions if the same file is re-uploaded.
                        if (!$transactionId) {
                            $transactionId = RandomString::generate(32, RandomString::CHAR_LOWER.RandomString::CHAR_NUMERIC);
                        }

                        $transactions[] = [
                            'transaction_id' => $transactionId,
                            'description' => $transaction->getText(),
                            'date' => $date,
                            'amount' => $amount,
                            'payment_reference_number' => $transaction->getCustomerReferenceNumber(),
                        ];
                    }
                }
            }

            return $transactions;
        } catch (Exception $e) {
            throw new ValidationException($e->getMessage());
        }
    }

    public function importRecord(array $record, array $options): ImportRecordResult
    {
        // Create the bank feed transaction
        $bankFeedTransaction = new BankFeedTransaction();
        foreach ($record as $key => $value) {
            $bankFeedTransaction->$key = $value;
        }

        try {
            $this->operation->create($bankFeedTransaction);
        } catch (ModelException $e) {
            throw new RecordException($e->getMessage());
        }

        // If the original bank feed transaction model was not persisted
        // then that means it
        if (!$bankFeedTransaction->persisted()) {
            return new ImportRecordResult();
        }

        return new ImportRecordResult($bankFeedTransaction, ImportRecordResult::CREATE);
    }
}
