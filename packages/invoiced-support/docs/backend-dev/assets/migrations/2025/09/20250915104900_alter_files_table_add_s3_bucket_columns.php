<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AlterFilesTableAddS3BucketColumns extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->ensureInstant();

        $this->table('Files')
            ->addColumn('bucket_name', 'string', [
                'null' => true,
                'default' => null,
                'after' => 'url',
                'length' => 150,
            ])
            ->addColumn('bucket_region', 'string', [
                'null' => true,
                'default' => null,
                'after' => 'bucket_name',
                'length' => 50,
            ])
            ->addColumn('s3_environment', 'string', [
                'null' => true,
                'default' => null,
                'after' => 'bucket_region',
                'length' => 50,
            ])
            ->addColumn('key', 'string', [
                'null' => true,
                'default' => null,
                'after' => 's3_environment',
                'length' => 250,
            ])
            ->update();

        $this->ensureInstantEnd();

        $this->table('Files')->addIndex(['key'])->update();
    }
}
