<?php

namespace App\AccountsReceivable\ClientView;

use App\AccountsReceivable\Models\CreditNote;
use App\Core\Authentication\Libs\UserContext;
use App\CustomerPortal\Libs\CustomerPortal;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CreditNoteClientViewVariables extends AbstractDocumentClientViewVariables
{
    public function __construct(
        private UserContext $userContext,
        UrlGeneratorInterface $urlGenerator,
        EmailBodyStorageInterface $storage,
    ) {
        parent::__construct($urlGenerator, $storage);
    }

    /**
     * Makes the view parameters for a credit note to be used
     * in the client view.
     */
    public function make(CreditNote $creditNote, CustomerPortal $portal, Request $request): array
    {
        return array_merge(
            $this->makeForDocument($creditNote, $request),
            [
                'amountCredited' => $creditNote->amount_credited,
                'amountRefunded' => $creditNote->amount_refunded,
                'balance' => $creditNote->balance,
                'commentsUrl' => $this->makeCommentsUrl($portal, $creditNote),
                'saveUrl' => null, // Currently disabled
                'terms' => $creditNote->theme()->terms,
            ]
        );
    }

    private function makeCommentsUrl(CustomerPortal $portal, CreditNote $creditNote): string
    {
        return $this->generatePortalUrl($portal, 'customer_portal_credit_note_send_message', [
            'id' => $creditNote->client_id,
        ]);
    }

    public function makeSaveUrl(CustomerPortal $portal, CreditNote $creditNote): ?string
    {
        $company = $creditNote->tenant();
        $customer = $creditNote->customer();
        $user = $this->userContext->get();
        $isMember = $user && $company->isMember($user);
        if (!$isMember && !$customer->network_connection && 'person' != $customer->type) {
            return $this->urlGenerator->generate('client_view_save_credit_note', [
                'companyId' => $company->identifier,
                'id' => $creditNote->client_id,
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return null;
    }
}
