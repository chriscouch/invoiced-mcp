<?php

namespace App\Core\Files\Libs;

use App\Core\Files\Models\Attachment;
use App\Core\Files\Models\File;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Model;

class AttachmentUploader extends AbstractS3Uploader
{
    /**
     * Attaches an uploaded file object to a given model object. This object must use `ApiObjectTrait`.
     *
     * @throws ModelException
     */
    public function attachToObject(Model $object, File $file, ?int $tenantId = null): void
    {
        $attachment = new Attachment();
        if ($tenantId) {
            $attachment->tenant_id = $tenantId;
        }
        $attachment->setParent($object);
        $attachment->setFile($file);
        $attachment->saveOrFail();
    }
}
