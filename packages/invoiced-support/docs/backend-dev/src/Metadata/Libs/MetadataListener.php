<?php

namespace App\Metadata\Libs;

use App\Metadata\Exception\MetadataStorageException;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Core\Orm\Event\ModelUpdated;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Model;
use App\Core\Orm\Event\AbstractEvent;
use App\PaymentProcessing\Gateways\StripeGateway;

class MetadataListener
{
    const MAX_COUNT = 10;
    const MAX_KEY_SIZE = 40;
    const MAX_VALUE_SIZE = 255;

    private static MetadataListener $listener;

    /**
     * Handles model creating or updating events.
     */
    public function beforeChange(AbstractEvent $event): void
    {
        /** @var MetadataModelInterface $model */
        $model = $event->getModel();

        try {
            $this->checkMetadata($model->metadata);
        } catch (\Exception $e) {
            throw new ListenerException($e->getMessage(), ['field' => 'metadata']);
        }
    }

    /**
     * Handles model created or updated events.
     */
    public function afterChange(AbstractEvent $event, string $eventName): void
    {
        /** @var MetadataModelInterface $model */
        $model = $event->getModel();

        $isUpdate = ModelUpdated::getName() == $eventName;
        $this->saveMetadata($model, $isUpdate);
    }

    /**
     * Handles model deleted events.
     */
    public function afterDelete(AbstractEvent $event): void
    {
        /** @var MetadataModelInterface $model */
        $model = $event->getModel();
        foreach ($model->getMetadataWriters() as $storage) {
            $storage->delete($model);
        }
    }

    /**
     * Sanitizes a given metadata map. Metadata cannot have keys
     * longer than 40 characters or values longer than 255
     * characters. There is a limit of 10 keys per object.
     *
     * @param object $metadata
     *
     * @throws \Exception
     */
    private function checkMetadata($metadata): void
    {
        if (!is_object($metadata)) {
            throw new \Exception('Metadata must be an object');
        }

        // check the number of metadata values does not exceed max
        //we filter out system metadata keys
        $numKeys = count(array_diff_key((array) $metadata, [StripeGateway::METADATA_STRIPE_CUSTOMER => true]));
        if ($numKeys > self::MAX_COUNT) {
            throw new \Exception('There can only be up to '.self::MAX_COUNT.' metadata values. '.$numKeys.' values were provided.');
        }

        foreach ((array) $metadata as $key => $value) {
            // check the key length
            if (strlen($key) > self::MAX_KEY_SIZE) {
                throw new \Exception('The `'.$key.'` metadata key exceeds the '.self::MAX_KEY_SIZE.' character limit.');
            }

            // encode array/object values to JSON
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            // check the value type
            if (!is_numeric($value) && !is_bool($value) && !is_string($value)) {
                throw new \Exception('The provided value for metadata.'.$key.' is an invalid type. Valid types are string, boolean, number, object, and array.');
            }

            // check the value length
            if (strlen((string) $value) > self::MAX_VALUE_SIZE) {
                throw new \Exception('The provided value for metadata.'.$key.' exceeds the '.self::MAX_VALUE_SIZE.' character limit.');
            }
        }
    }

    /**
     * Saves the metadata on this model.
     */
    private function saveMetadata(MetadataModelInterface $model, bool $isUpdate): bool
    {
        $metadata = $model->getMetadataToBeSaved();
        if (!is_object($metadata)) {
            return true;
        }

        // save to all currently used metadata data stores
        foreach ($model->getMetadataWriters() as $storage) {
            try {
                $storage->save($model, $metadata, $isUpdate);
            } catch (MetadataStorageException $e) {
                throw new ListenerException($e->getMessage(), ['field' => 'metadata']);
            }
        }

        $model->hydrateMetadata($metadata);

        return true;
    }

    /**
     * Installs the metadata listener on a model.
     */
    public static function add(Model $model): void
    {
        if (!isset(self::$listener)) {
            self::$listener = new self();
        }

        $model::saving([self::$listener, 'beforeChange']);
        $model::saved([self::$listener, 'afterChange']);
        $model::deleted([self::$listener, 'afterDelete']);
    }
}
