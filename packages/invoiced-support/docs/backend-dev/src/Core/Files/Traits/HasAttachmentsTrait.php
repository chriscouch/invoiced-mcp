<?php

namespace App\Core\Files\Traits;

use App\Core\Files\Models\Attachment;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Utils\Enums\ObjectType;

/**
 * @property array $attachments
 */
trait HasAttachmentsTrait
{
    protected ?array $_saveAttachments = null;
    protected ?int $_pdfAttachment = null;

    /**
     * Saves the given attachments.
     *
     * @throws ListenerException
     */
    protected function saveAttachments(bool $isUpdate): void
    {
        if (null === $this->_saveAttachments) {
            return;
        }

        $success = true;
        $ids = [];

        // get the existing attachments
        $existing = [];
        if ($isUpdate) {
            $existing = self::getDriver()->getConnection(null)->createQueryBuilder()
                ->select('file_id')
                ->from('Attachments')
                ->andWhere('tenant_id = '.$this->tenant_id)
                ->andWhere('parent_type = "'.ObjectType::fromModel($this)->typeName().'"')
                ->andWhere('parent_id = '.$this->id())
                ->fetchFirstColumn();
        }

        foreach (array_filter($this->_saveAttachments) as $fileId) {
            $ids[] = $fileId;

            // only update location on existing attachments
            if (in_array($fileId, $existing)) {
                $attachment = Attachment::where('file_id', $fileId)
                    ->where('parent_type', ObjectType::fromModel($this)->typeName())
                    ->where('parent_id', $this)
                    ->oneOrNull();

                $location = ($this->_pdfAttachment == $fileId) ? Attachment::LOCATION_PDF : Attachment::LOCATION_ATTACHMENT;

                if ($attachment && $location != $attachment->location) {
                    $attachment->location = $location;
                    if (!$attachment->save()) {
                        throw new ListenerException('Could not save attachment: '.$attachment->getErrors(), ['field' => 'attachments']);
                    }
                }

                continue;
            }

            // save the attachment
            $attachment = new Attachment();
            $attachment->tenant_id = $this->tenant_id;
            $attachment->setParent($this);
            $attachment->file_id = $fileId;

            if ($this->_pdfAttachment == $fileId) {
                $attachment->location = Attachment::LOCATION_PDF;
            } else {
                $attachment->location = Attachment::LOCATION_ATTACHMENT;
            }

            $success = $attachment->save() && $success;
        }

        // delete all other attachments for this document
        if ($isUpdate) {
            $query = self::getDriver()->getConnection(null)->createQueryBuilder()
                ->delete('Attachments')
                ->andWhere('tenant_id = '.$this->tenant_id)
                ->andWhere('parent_type = "'.ObjectType::fromModel($this)->typeName().'"')
                ->andWhere('parent_id = '.$this->id());

            if (count($ids) > 0) {
                $query->andWhere('file_id NOT IN ('.implode(',', $ids).')');
            }

            $query->executeStatement();
        }

        $this->_saveAttachments = null;
        $this->_pdfAttachment = null;
    }
}
