<?php

namespace App\Integrations\NetSuite\Writers;

use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;

/**
 * Class NetSuiteNoteAdapter.
 *
 * @template-extends    AbstractNetSuiteObjectWriter<AccountingWritableModelInterface>
 *
 * @property AccountingWritableModelInterface $model
 */
class NullWriterNetSuite extends AbstractNetSuiteObjectWriter
{
    public function getDeploymentId(): string
    {
        return 'none';
    }

    public function getScriptId(): string
    {
        return 'none';
    }

    public function toArray(): array
    {
        return [];
    }

    public function getReverseMapping(): ?string
    {
        return null;
    }

    public function shouldCreate(): bool
    {
        return false;
    }

    public function shouldUpdate(): bool
    {
        return false;
    }
}
