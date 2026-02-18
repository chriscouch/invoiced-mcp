<?php

namespace App\Network\Ubl\ViewModel;

use App\Network\Enums\NetworkDocumentType;
use Carbon\CarbonImmutable;
use Money\Money;

class DocumentViewModel
{
    private NetworkDocumentType $type;
    private string $reference;
    protected ?Money $summaryTotal = null;
    private array $attachments = [];
    private CarbonImmutable $issueDate;

    public function getType(): NetworkDocumentType
    {
        return $this->type;
    }

    public function setType(NetworkDocumentType $type): void
    {
        $this->type = $type;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): void
    {
        $this->reference = $reference;
    }

    public function getIssueDate(): CarbonImmutable
    {
        return $this->issueDate;
    }

    public function setIssueDate(CarbonImmutable $issueDate): void
    {
        $this->issueDate = $issueDate;
    }

    public function getSummaryTotal(): ?Money
    {
        return $this->summaryTotal;
    }

    public function setSummaryTotal(?Money $total): void
    {
        $this->summaryTotal = $total;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function setAttachments(array $attachments): void
    {
        $this->attachments = $attachments;
    }

    public function addAttachment(array $attachment): void
    {
        $this->attachments[] = $attachment;
    }
}
