<?php

namespace App\Core\Files\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\RestApi\Traits\ApiObjectTrait;
use Carbon\CarbonImmutable;

/**
 * @property int    $id
 * @property string $name
 * @property int    $size
 * @property string $type
 * @property string $url
 * @property string $bucket_name
 * @property string $bucket_region
 * @property string $s3_environment
 * @property string $key
 */
class File extends MultitenantModel
{
    use ApiObjectTrait;
    use AutoTimestamps;

    private string $content;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property( // filename
                required: true,
            ),
            'size' => new Property( // in bytes
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'type' => new Property( // mimetype
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'url' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['callable', 'fn' => [self::class, 'validateUrl']],
            ),
            'bucket_name' => new Property(
                required: false
            ),
            'bucket_region' => new Property(
                required: false
            ),
            's3_environment' => new Property(
                required: false
            ),
            'key' => new Property(
                required: false
            ),
        ];
    }

    protected function getUrlValue(string $url): string
    {
        // When an attachment is stored in our S3 bucket, make sure the URL
        // is properly encoded to deal with protected characters like `#` and `=`
        if (str_contains($url, 'invoiced') && str_contains($url, 'amazonaws.com/') && CarbonImmutable::createFromTimeString('Aug 4, 2023, 1:54 PM')->greaterThanOrEqualTo(CarbonImmutable::createFromTimestamp((int) $this->created_at))) {
            $parts = explode('/', $url);
            $url = implode('/', array_slice($parts, 0, -1));
            $url .= '/'.urlencode($parts[count($parts) - 1]);
        }

        return $url;
    }

    /**
     * Validates a URL value. Only URLs that start with http/https are valid.
     *
     * @param string $url
     */
    public static function validateUrl($url): bool
    {
        if (!str_starts_with((string) $url, 'http')) {
            return false;
        }

        return true;
    }

    public function getContent(): string
    {
        if (isset($this->content)) {
            return $this->content;
        }

        return (string) @file_get_contents($this->url);
    }

    /**
     * Sets the "content" of the file. This should only
     * be used for testing.
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getBucketName(): string
    {
        return $this->bucket_name;

    }

    public function setBucketName(string $bucketName): void
    {
        $this->bucket_name = $bucketName;
    }

    public function getBucketRegion(): string
    {
        return $this->bucket_region;

    }

    public function setBucketRegion(string $bucketRegion): void
    {
        $this->bucket_region = $bucketRegion;
    }

    public function getS3Environment(): string
    {
        return $this->s3_environment;

    }

    public function setS3Environment(string $s3Environment): void
    {
        $this->s3_environment = $s3Environment;
    }

    public function getKey(): string
    {
        return $this->key;

    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }
}
