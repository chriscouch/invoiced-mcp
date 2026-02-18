<?php

namespace App\CustomerPortal\ValueObjects;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Models\PaymentLinkSession;
use App\CashApplication\Models\Payment;
use App\PaymentProcessing\Models\PaymentFlow;

class PaymentLinkResult
{
    private PaymentFlow $paymentFlow;
    private Customer $customer;
    private Invoice $invoice;
    private ?Payment $payment;
    private PaymentLinkSession $session;
    private string $redirectUrl;

    public function __construct(
        public readonly PaymentLink $paymentLink,
    ) {
    }

    public function getPaymentFlow(): PaymentFlow
    {
        return $this->paymentFlow;
    }

    public function setPaymentFlow(PaymentFlow $paymentFlow): void
    {
        $this->paymentFlow = $paymentFlow;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(Invoice $invoice): void
    {
        $this->invoice = $invoice;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): void
    {
        $this->payment = $payment;
    }

    public function getSession(): PaymentLinkSession
    {
        return $this->session;
    }

    public function setSession(PaymentLinkSession $session): void
    {
        $this->session = $session;
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    public function setRedirectUrl(string $redirectUrl): void
    {
        $this->redirectUrl = $redirectUrl;
    }
}
