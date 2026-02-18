<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Enums\PaymentLinkStatus;
use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Models\PaymentLinkItem;
use App\AccountsReceivable\Models\PaymentLinkSession;
use App\Core\I18n\ValueObjects\Money;
use App\CustomerPortal\Exceptions\PaymentLinkException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class PaymentLinkHelper
{
    /**
     * Gets the payment link for a given public identifier.
     *
     * @throws PaymentLinkException|NotFoundHttpException
     */
    public static function getPaymentLink(TranslatorInterface $translator, string $clientId, string $hash): PaymentLink
    {
        $link = PaymentLink::findClientId($clientId);
        if (!$link || PaymentLinkStatus::Deleted == $link->status) {
            throw new NotFoundHttpException();
        }

        if (PaymentLinkStatus::Active != $link->status) {
            throw new PaymentLinkException($translator->trans('messages.already_paid', [], 'customer_portal'));
        }

        // When there is a hash of query parameters provided,
        // then we perform a duplicate check to see if the payment
        // link has already been paid using the given query parameters.
        if ($link->reusable && $hash) {
            $n = PaymentLinkSession::where('payment_link_id', $link)
                ->where('hash', $hash)
                ->count();
            if ($n > 0) {
                throw new PaymentLinkException($translator->trans('messages.already_paid', [], 'customer_portal'));
            }
        }

        return $link;
    }

    /**
     * Builds the line items for a payment link.
     */
    public static function getLineItems(PaymentLink $paymentLink, Money $totalAmount, string $defaultLineItemName): array
    {
        $items = PaymentLinkItem::where('payment_link_id', $paymentLink->id)->all();

        if ($items->count()) {
            $tmpItems = [];
            foreach ($items as $item) {
                $tmpItems[] = [
                    'name' => $item->description ?? $defaultLineItemName,
                    'quantity' => 1,
                    'unit_cost' => $item->amount,
                ];
            }

            return $tmpItems;
        }

        return [
            [
                'name' => $defaultLineItemName,
                'quantity' => 1,
                'unit_cost' => $totalAmount->toDecimal(),
            ],
        ];
    }
}
