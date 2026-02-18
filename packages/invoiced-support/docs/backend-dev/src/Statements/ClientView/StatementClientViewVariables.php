<?php

namespace App\Statements\ClientView;

use App\AccountsReceivable\Libs\CustomerBalanceGenerator;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Traits\CustomerPortalViewVariablesTrait;
use App\AccountsReceivable\ValueObjects\CustomerBalance;
use App\Companies\Models\Company;
use App\CustomerPortal\Libs\CustomerPortal;
use App\Statements\Libs\AbstractStatement;
use App\Statements\Libs\BalanceForwardStatement;
use App\Statements\Libs\OpenItemStatement;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StatementClientViewVariables
{
    use CustomerPortalViewVariablesTrait;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private CustomerBalanceGenerator $balanceGenerator,
    ) {
    }

    /**
     * Makes the view parameters for a statement to be used
     * in the client view.
     */
    public function make(AbstractStatement $statement, CustomerPortal $portal, Request $request): array
    {
        $company = $statement->getThemeCompany();
        $customer = $statement->customer;
        $showCountry = $customer->country && $company->country != $customer->country;
        $theme = $statement->theme();

        // Generate the balance
        $balance = $this->balanceGenerator->generate($customer);

        // show the customer column if there are any sub-customers
        $showCustomer = Customer::where('parent_customer', $customer)->count() > 0;

        return [
            'billFrom' => $this->makeBillFrom($company, $showCountry),
            'billTo' => $this->makeBillTo($customer, $showCountry, $theme->show_customer_no),
            'type' => $statement->type,
            'startDate' => ($statement->start) ? CarbonImmutable::createFromTimestamp($statement->start) : null,
            'endDate' => $statement->end ? CarbonImmutable::createFromTimestamp($statement->end) : null,
            'pastDueOnly' => $statement->pastDueOnly,
            'owesMoney' => $statement->balance > 0,
            'currency' => $statement->currency,
            'customFields' => $this->makeCustomFields($customer, $customer),
            'showCustomer' => $showCustomer,
            'lineItems' => $this->makeLineItems($statement),
            'previousBalance' => $statement->previousBalance,
            'totalPaid' => $statement->totalPaid,
            'total' => $statement->totalInvoiced,
            'balance' => $statement->balance,
            'creditBalance' => $statement->creditBalance,
            'downloadUrl' => $this->makeDownloadUrl($request, $company, $customer),
            'paymentUrl' => $this->makePaymentUrl($portal, $balance),
            'months' => $this->buildMonths($customer),
            'selectedMonth' => $request->query->get('month') ?? date('Y-m'),
        ];
    }

    private function makeDownloadUrl(Request $request, Company $company, Customer $customer): string
    {
        return $this->urlGenerator->generate('client_view_statement_pdf', array_merge(
            $request->query->all(),
            [
                'companyId' => $company->identifier,
                'id' => $customer->client_id,
                'locale' => $request->getLocale(),
            ]), UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function makePaymentUrl(CustomerPortal $portal, CustomerBalance $balance): ?string
    {
        if ($balance->totalOutstanding->isPositive()) {
            return $this->generatePortalUrl($portal, 'customer_portal_payment_select_items_form');
        }

        return null;
    }

    private function makeLineItems(AbstractStatement $statement): array
    {
        if ($statement instanceof OpenItemStatement) {
            return $this->makeLineItemsOpenItem($statement);
        }

        if ($statement instanceof BalanceForwardStatement) {
            return $this->makeLineItemsBalanceForward($statement);
        }

        return [];
    }

    private function makeLineItemsOpenItem(OpenItemStatement $statement): array
    {
        $lineItems = [];
        foreach ($statement->accountDetail as $line) {
            $lineItems[] = [
                'date' => CarbonImmutable::createFromTimestamp($line['date']),
                'customer' => $line['customer']?->name,
                'description' => $line['number'],
                'url' => $line['url'],
                'total' => $line['total'],
                'balance' => $line['balance'],
                'dueDate' => $line['dueDate'] ? CarbonImmutable::createFromTimestamp($line['dueDate']) : null,
            ];
        }

        return $lineItems;
    }

    private function makeLineItemsBalanceForward(BalanceForwardStatement $statement): array
    {
        $lineItems = [];
        foreach ($statement->unifiedDetail as $line) {
            $lineItems[] = [
                'date' => CarbonImmutable::createFromTimestamp($line['date']),
                'customer' => $line['customer']?->name,
                'description' => $line['number'] ?? $line['type'],
                'url' => $line['url'] ?? null,
                'amount' => $line['amount'],
                'balance' => $line['balance'] ?? null,
            ];
        }

        return $lineItems;
    }

    private function buildMonths(Customer $customer): array
    {
        // The month selector will go back to the earliest of
        // the customer created date or the oldest invoice date.
        // This date will be at minimum 1 month ago and
        // at most 3 years ago.
        $customerCreated = $customer->created_at;
        $oldestInvoiceDate = Invoice::where('customer', $customer->id())->min('date');
        $stop = min($oldestInvoiceDate, $customerCreated);
        $stop = max($stop, strtotime('-3 years'));
        $stop = min($stop, strtotime('-1 month'));
        $stop = date('Ym', (int) $stop);

        $months = [];
        $current = time();
        while (date('Ym', $current) >= $stop) {
            $months[] = [
                'id' => date('Y-m', $current),
                'name' => date('F Y', $current),
            ];
            $current = strtotime('-1 month', $current);
        }

        return $months;
    }
}
