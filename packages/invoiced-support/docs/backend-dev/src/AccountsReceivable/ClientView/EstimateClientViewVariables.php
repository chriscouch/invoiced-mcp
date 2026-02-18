<?php

namespace App\AccountsReceivable\ClientView;

use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\ValueObjects\EstimateStatus;
use App\Core\Authentication\Libs\UserContext;
use App\Core\I18n\ValueObjects\Money;
use App\CustomerPortal\Libs\CustomerPortal;
use App\PaymentProcessing\Libs\PaymentFlowManager;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EstimateClientViewVariables extends AbstractDocumentClientViewVariables
{
    public function __construct(
        private readonly UserContext $userContext,
        private readonly PaymentFlowManager $paymentFlowManager,
        UrlGeneratorInterface $urlGenerator,
        EmailBodyStorageInterface $storage,
    ) {
        parent::__construct($urlGenerator, $storage);
    }

    /**
     * Makes the view parameters for an estimate to be used
     * in the client view.
     */
    public function make(Estimate $estimate, CustomerPortal $portal, Request $request): array
    {
        return array_merge(
            $this->makeForDocument($estimate, $request),
            [
                'paymentTerms' => $estimate->payment_terms,
                'approved' => $estimate->approved,
                'deposit' => $estimate->deposit,
                'depositPaid' => $estimate->deposit_paid,
                'commentsUrl' => $this->makeCommentsUrl($portal, $estimate),
                'approveUrl' => $this->makeApproveUrl($portal, $estimate),
                'paymentUrl' => $this->makePaymentUrl($portal, $estimate),
                'saveUrl' => null, // Currently disabled
                'expirationDate' => $estimate->expiration_date ? CarbonImmutable::createFromTimestamp($estimate->expiration_date) : null,
                'terms' => $estimate->theme()->estimate_footer,
            ]
        );
    }

    private function makeCommentsUrl(CustomerPortal $portal, Estimate $estimate): string
    {
        return $this->generatePortalUrl($portal, 'customer_portal_estimate_send_message', [
            'id' => $estimate->client_id,
        ]);
    }

    private function makeApproveUrl(CustomerPortal $portal, Estimate $estimate): ?string
    {
        $amount = $this->paymentFlowManager->getBlockingAmount($estimate, Money::fromDecimal($estimate->currency, $estimate->deposit));
        if (!$amount->isZero()) {
            return null;
        }

        if (!$estimate->approved && !$estimate->closed && EstimateStatus::EXPIRED != $estimate->status) {
            return $this->generatePortalUrl($portal, 'customer_portal_estimate_approval_form', [
                'companyId' => $estimate->tenant()->identifier,
                'id' => $estimate->client_id,
            ]);
        }

        return null;
    }

    private function makePaymentUrl(CustomerPortal $portal, Estimate $estimate): ?string
    {
        if ($estimate->approved && $estimate->deposit && !$estimate->deposit_paid) {
            return $this->generatePortalUrl($portal, 'customer_portal_payment_form', [
                'estimates' => [$estimate->client_id],
            ]);
        }

        return null;
    }

    public function makeSaveUrl(CustomerPortal $portal, Estimate $estimate): ?string
    {
        $company = $estimate->tenant();
        $customer = $estimate->customer();
        $user = $this->userContext->get();
        $isMember = $user && $company->isMember($user);
        if (!$isMember && !$customer->network_connection && 'person' != $customer->type) {
            return $this->urlGenerator->generate('client_view_save_estimate', [
                'companyId' => $company->identifier,
                'id' => $estimate->client_id,
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return null;
    }
}
