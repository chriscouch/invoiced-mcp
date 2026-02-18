<?php

namespace App\CustomerPortal\Libs;

use App\CustomerPortal\Cards\ActiveSubscriptionsCard;
use App\CustomerPortal\Cards\AgingCard;
use App\CustomerPortal\Cards\BalanceDueCard;
use App\CustomerPortal\Cards\BillingDetailsCard;
use App\CustomerPortal\Cards\PaymentMethodCard;
use App\CustomerPortal\Cards\PaymentPlanCard;
use App\CustomerPortal\Cards\RecentPaymentsCard;
use App\CustomerPortal\Interfaces\CardInterface;
use App\Themes\Models\Template;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ServiceLocator;

class CardFactory
{
    private const CARDS = [
        'activeSubscriptions' => ActiveSubscriptionsCard::class,
        'aging' => AgingCard::class,
        'balanceDue' => BalanceDueCard::class,
        'billingDetails' => BillingDetailsCard::class,
        'paymentMethod' => PaymentMethodCard::class,
        'paymentPlan' => PaymentPlanCard::class,
        'recentPayments' => RecentPaymentsCard::class,
    ];

    private const DEFAULT_CARD_LAYOUT = '[
  {
    "width": 6,
    "cards": [
      "balanceDue",
      "paymentPlan",
      "aging",
      "activeSubscriptions"
    ]
  },
  {
    "width": 6,
    "cards": [
      "billingDetails",
      "paymentMethod",
      "recentPayments"
    ]
  }
]';

    public function __construct(private ServiceLocator $cardLocator)
    {
    }

    public function getCardLayout(): array
    {
        $cardJson = Template::getContent('billing_portal/card_layout.json') ?? self::DEFAULT_CARD_LAYOUT;

        return json_decode($cardJson, true);
    }

    public function makeCardData(array $cardLayout, CustomerPortal $portal): array
    {
        $cardData = [];
        foreach ($cardLayout as $column) {
            foreach ($column['cards'] as $cardId) {
                if (!isset($cardData[$cardId])) {
                    $card = $this->get($cardId);
                    $cardData[$cardId] = $card->getData($portal);
                }
            }
        }

        return $cardData;
    }

    public function get(string $id): CardInterface
    {
        if (!isset(self::CARDS[$id])) {
            throw new InvalidArgumentException('Card does not exist: '.$id);
        }

        return $this->cardLocator->get(self::CARDS[$id]);
    }
}
