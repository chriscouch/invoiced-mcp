<?php

namespace App\PaymentProcessing\Ach;

use App\PaymentProcessing\Models\AchFileFormat;
use Carbon\CarbonImmutable;
use Nacha\Exception;
use Nacha\File;
use Nacha\Batch;
use Nacha\Record\Entry;

/**
 * Generates a valid NACHA payment file to be sent to a bank.
 *
 * Details about the ACH file format: https://achdevguide.nacha.org/
 */
class AchFileGenerator
{
    /**
     * Creates a debit ACH file for the given input and formatting parameters.
     *
     * @param Entry[] $entries
     *
     * @throws Exception
     */
    public function makeDebits(AchFileFormat $format, CarbonImmutable $effectiveDate, array $entries): string
    {
        // 220 = Credits Only, 225 = Debits only
        return $this->make($format, $effectiveDate, '225', $entries);
    }

    /**
     * Creates a credit ACH file for the given input and formatting parameters.
     *
     * @param Entry[] $entries
     *
     * @throws Exception
     */
    public function makeCredits(AchFileFormat $format, CarbonImmutable $effectiveDate, array $entries): string
    {
        // 220 = Credits Only, 225 = Debits only
        return $this->make($format, $effectiveDate, '220', $entries);
    }

    /**
     * Creates an ACH file for the given input and formatting parameters.
     *
     * @param Entry[] $entries
     *
     * @throws Exception
     */
    public function make(AchFileFormat $format, CarbonImmutable $effectiveDate, string $serviceClassCode, array $entries): string
    {
        // Create the file and set the proper header info
        $file = new File();
        $file->getHeader()
            ->setPriorityCode('01')
            ->setImmediateDestination($format->immediate_destination)
            ->setImmediateDestinationName($format->immediate_destination_name)
            ->setImmediateOrigin($format->immediate_origin)
            ->setImmediateOriginName($format->immediate_origin_name)
            ->setFileCreationDate(date('Ymd'))
            ->setFileCreationTime(date('Hi'));

        // Create a batch and add some entries
        $batch = new Batch();
        $batch->getHeader()
            ->setServiceClassCode($serviceClassCode)
            ->setCompanyName($format->company_name)
            ->setCompanyId($format->company_id)
            ->setCompanyDiscretionaryData($format->company_discretionary_data)
            ->setStandardEntryClassCode($format->default_sec_code)
            ->setCompanyEntryDescription($format->company_entry_description)
            ->setOriginatingDFiId($format->originating_dfi_identification)
            ->setOriginatorStatusCode('1')
            ->setBatchNumber('0000001')
            ->setEffectiveEntryDate($effectiveDate->format('ymd'));

        foreach ($entries as $entry) {
            $batch->addEntry($entry);
        }

        $file->addBatch($batch);

        return (string) $file;
    }
}
