<?php

namespace App\Network\Ubl\ViewModel;

use App\Network\Ubl\Traits\LegalMonetaryTotalTrait;

final class CreditNoteViewModel extends DocumentViewModel
{
    use LegalMonetaryTotalTrait;

    private ?string $billFrom = null;
    private ?string $billTo = null;
    private ?string $shipTo = null;
    private ?string $paymentTerms = null;
    private ?string $purchaseOrder = null;
    private ?string $notes = null;
    private array $lineItems = [];

    public function getBillFrom(): ?string
    {
        return $this->billFrom;
    }

    public function setBillFrom(?string $billFrom): void
    {
        $this->billFrom = $billFrom;
    }

    public function getBillTo(): ?string
    {
        return $this->billTo;
    }

    public function setBillTo(?string $billTo): void
    {
        $this->billTo = $billTo;
    }

    public function getShipTo(): ?string
    {
        return $this->shipTo;
    }

    public function setShipTo(?string $shipTo): void
    {
        $this->shipTo = $shipTo;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getPaymentTerms(): ?string
    {
        return $this->paymentTerms;
    }

    public function setPaymentTerms(?string $paymentTerms): void
    {
        $this->paymentTerms = $paymentTerms;
    }

    public function getPurchaseOrder(): ?string
    {
        return $this->purchaseOrder;
    }

    public function setPurchaseOrder(?string $purchaseOrder): void
    {
        $this->purchaseOrder = $purchaseOrder;
    }

    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    public function addLineItem(array $lineItem): void
    {
        $this->lineItems[] = $lineItem;
    }
}
