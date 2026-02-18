<?php

namespace App\EntryPoint\Controller\CustomerPortal;

use App\AccountsReceivable\ClientView\CreditNoteClientViewVariables;
use App\AccountsReceivable\Libs\DocumentViewTracker;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Files\Libs\AttachmentUploader;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Query;
use App\CustomerPortal\Command\SignInCustomer;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\Metadata\Models\CustomField;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use App\Sending\Email\Libs\CommentEmailWriter;
use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;

#[Route(
    name: 'customer_portal_',
    requirements: ['subdomain' => '^(?!api|tknz).*$'],
    host: '{subdomain}.%app.domain%',
    schemes: '%app.protocol%',
)]
class CreditNoteCustomerPortalController extends AbstractCustomerPortalController
{
    #[Route(path: '/credit_notes', name: 'list_credit_notes', methods: ['GET'])]
    public function listCreditNotes(Request $request): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        if (!$portal->enabled()) {
            throw new NotFoundHttpException();
        }

        $customer = $portal->getSignedInCustomer();
        if (!$customer) {
            return $this->redirectToLogin($request);
        }

        // parse the filter parameters
        $query = $this->buildCreditNotesQuery($portal->getAllowCustomerIds(), $request);
        $total = $query->count();
        $filter = $this->parseFilterParams($request, '/credit_notes', $total, 'date DESC');

        // execute the query
        $creditNotes = $this->getCreditNotes($query, $customer, $filter['perPage'], $filter['page'], $filter['sort'], $request);

        // build the totals
        $currency = $customer->calculatePrimaryCurrency();
        $totals = $this->calculateTotals($creditNotes, ['total', 'balance'], $currency);

        // show the customer column if there are any sub-customers
        $showCustomer = Customer::where('parent_customer', $customer)->count() > 0;

        return $this->render('customerPortal/creditNotes/list.twig', [
            'hasEstimates' => $portal->hasEstimates(),
            'showCustomer' => $showCustomer,
            'results' => $creditNotes,
            'total' => $total,
            'filter' => $filter,
            'sort' => $filter['sort'],
            'totals' => $totals,
        ]);
    }

    #[Route(path: '/credit_notes/{id}', name: 'view_credit_note', methods: ['GET'])]
    public function viewCreditNote(
        Request $request,
        SignInCustomer $signIn,
        UserContext $userContext,
        string $id,
        CreditNoteClientViewVariables $viewVariables,
        DocumentViewTracker $documentViewTracker): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        $company = $portal->company();

        $creditNote = CreditNote::findClientId($id);
        if (!$creditNote || $creditNote->voided) {
            throw new NotFoundHttpException();
        }

        // Check if the viewer has permission when "Require Authentication" is enabled
        $customer = $creditNote->customer();
        if ($response = $this->mustLogin($customer, $request)) {
            return $response;
        }

        // sign the customer into the customer portal temporarily
        $response = new Response();
        $response = $signIn->signIn($customer, $response, true);

        // record the view
        $this->trackDocumentView($creditNote, $request, $userContext, $documentViewTracker);

        return $this->render('customerPortal/creditNotes/view.twig', [
            'dateFormat' => $company->date_format,
            'document' => $viewVariables->make($creditNote, $portal, $request),
        ], $response);
    }

    #[Route(path: '/credit_notes/{id}/comments', name: 'credit_note_send_message', methods: ['POST'])]
    public function creditNoteSendMessage(Request $request, AttachmentUploader $uploader, CommentEmailWriter $emailWriter, CustomerPortalEvents $events, Connection $database, string $id, EmailBodyStorageInterface $storage): Response
    {
        $creditNote = CreditNote::findClientId($id);
        if (!$creditNote || $creditNote->voided) {
            throw new NotFoundHttpException();
        }

        // check the CSRF token
        if (!$this->isCsrfTokenValid('customer_portal_leave_comment', (string) $request->request->get('_csrf_token'))) {
            throw new TokenNotFoundException();
        }

        $email = (string) ($request->request->get('email') ?? $creditNote->customer()->email);
        $text = (string) $request->request->get('comment');
        $file = $request->files->get('file');

        try {
            $result = $this->sendMessage($creditNote, $email, $text, $file, $uploader, $emailWriter, $events, $storage);
        } catch (Exception $e) {
            $database->setRollbackOnly();
            $result = ['message' => $e->getMessage()];

            return new JsonResponse($result, 400);
        }

        return new JsonResponse($result);
    }

    private function buildCreditNotesQuery(array $ids, Request $request): Query
    {
        $query = CreditNote::query()
            ->with('customer')
            ->where('customer', $ids)
            ->where('draft', false)
            ->where('voided', false);

        if ($status = $request->query->get('status')) {
            if ('paid' == $status) {
                $query->where('paid', true);
            } elseif ('open' == $status) {
                $query->where('paid', false);
            }
        }

        $this->addSearchToQuery($query, $request);
        $this->addDateRangeToQuery($query, $request);

        return $query;
    }

    /**
     * Fetches the credit notes for the client portal view.
     */
    private function getCreditNotes(Query $query, Customer $customer, int $perPage, int $page, string $sort, Request $request): array
    {
        --$page;

        $query->start($perPage * $page)
            ->sort($sort);

        $fields = CustomField::where('object', ['credit_note'])
            ->where('external', true)
            ->all();

        $creditNotes = [];
        $dateFormat = $customer->tenant()->date_format;
        /** @var CreditNote $creditNote */
        foreach ($query->first($perPage) as $creditNote) {
            $balance = Money::fromDecimal($creditNote->currency, $creditNote->balance);
            $total = Money::fromDecimal($creditNote->currency, $creditNote->total);

            $creditNotes[] = [
                'client_id' => $creditNote->client_id,
                'number' => $creditNote->number,
                'customer' => [
                    'name' => $creditNote->customer()->name,
                ],
                'purchase_order' => $creditNote->purchase_order,
                'date' => date($dateFormat, $creditNote->date),
                'currency' => $creditNote->currency,
                'total' => $total->toDecimal(),
                '_total' => $total,
                'balance' => $balance->toDecimal(),
                '_balance' => $balance,
                'status' => $creditNote->status,
                'url' => $this->generatePortalContextUrl('customer_portal_view_credit_note', [
                    'id' => $creditNote->client_id,
                ]),
                'pdf_url' => $creditNote->pdf_url.'?locale='.$request->getLocale(),
                'metadata' => array_intersect_key((array) $creditNote->metadata, array_flip(array_column($fields->toArray(), 'id'))),
            ];
        }

        return $creditNotes;
    }
}
