<?php

namespace App\Integrations\Adyen\Reconciliation;

use App\Core\S3ProxyFactory;
use mikehaertl\tmp\File as TmpFile;

class AdyenReportStorage
{
    private mixed $s3;

    public function __construct(
        private string $environment,
        private string $bucket,
        S3ProxyFactory $s3Factory,
    ) {
        $this->s3 = $s3Factory->build();
    }

    public function store(TmpFile $tmpFile, string $filename): void
    {
        // NOTE: Exceptions are intentionally not caught here
        // in order to halt the report downloading process.
        $this->s3->putObject([
            'Bucket' => $this->bucket,
            'Key' => $this->generateKey($filename),
            'SourceFile' => $tmpFile->getFileName(),
        ]);
    }

    public function retrieve(string $filename): ?TmpFile
    {
        $key = $this->generateKey($filename);
        if (!$this->s3->doesObjectExist($this->bucket, $key)) {
            return null;
        }

        // NOTE: Exceptions are intentionally not caught here
        // in order to halt the report processing.
        $object = $this->s3->getObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);

        $tmpFile = new TmpFile('');
        file_put_contents($tmpFile->getFileName(), $object['Body']);

        return $tmpFile;
    }

    private function generateKey(string $filename): string
    {
        return $this->environment.'/'.$filename;
    }
}
