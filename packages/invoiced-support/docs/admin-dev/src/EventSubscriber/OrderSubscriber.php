<?php

namespace App\EventSubscriber;

use App\Entity\CustomerAdmin\NewAccount;
use App\Entity\CustomerAdmin\Order;
use App\Entity\CustomerAdmin\User;
use App\Entity\Invoiced\BillingProfile;
use App\Enums\BillingSystem;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Event\PostOrderEvent;
use App\Event\PreOrderEvent;
use App\Service\NewCompanyCreator;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Invoiced\Client;
use RuntimeException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;
use Vich\UploaderBundle\Exception\NoFileFoundException;
use Vich\UploaderBundle\Storage\StorageInterface;

class OrderSubscriber implements EventSubscriberInterface
{
    private const SOW_ITEM = 'implementation-fee';

    public function __construct(
        private NewCompanyCreator $creator,
        private ManagerRegistry $doctrine,
        private string $newOrderEmail,
        private MailerInterface $mailer,
        private Client $invoiced,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
        private StorageInterface $fileStorage)
    {
    }

    public function onPreOrderEvent(PreOrderEvent $event): void
    {
        $order = $event->getOrder();
        $orderType = $order->getTypeEnum();

        // Imported orders do nothing
        if (in_array($orderType, [OrderType::Imported])) {
            $order->setStatus(OrderStatus::Complete->value);

            return;
        }

        // New account orders require additional information
        if ($orderType->hasNewAccount()) {
            $order->setStatus(OrderStatus::MissingInfo->value);
        }

        // Ensure the order has a billing profile with Invoiced as the billing system
        $billingProfile = $this->getBillingProfile($order);
        if (!in_array($billingProfile->getBillingSystemEnum(), [BillingSystem::Invoiced, BillingSystem::Reseller]) || !$billingProfile->getInvoicedCustomer()) {
            throw new RuntimeException('You must select a billing profile that is billed through Invoiced or a reseller.');
        }

        // If this is a statement of work then bill it now
        if (OrderType::StatementOfWork == $orderType) {
            $this->billSow($billingProfile, $order);
        }
    }

    public function onPostOrderEvent(PostOrderEvent $event): void
    {
        $order = $event->getOrder();
        $orderType = $order->getTypeEnum();

        // Imported orders do nothing
        if (in_array($orderType, [OrderType::Imported])) {
            return;
        }

        if ($newAccount = $order->getNewAccount()) {
            $this->provisionNewAccount($newAccount, $order);
        }

        $this->sendToZapier($order);

        if ($orderType->isChangeOrder()) {
            $this->sendChangeOrderEmailNotification($order);
        }

        if (in_array($orderType, [OrderType::StatementOfWork])) {
            $this->markFulfilled($order);
        }

        $this->sendEmailNotification($order);
    }

    private function getBillingProfile(Order $order): BillingProfile
    {
        $repository = $this->doctrine->getRepository(BillingProfile::class);
        $billingProfile = $repository->find($order->getBillingProfileId());
        if (!$billingProfile instanceof BillingProfile) {
            throw new RuntimeException('Could not find a billing profile with ID: '.$order->getBillingProfileId());
        }

        return $billingProfile;
    }

    private function billSow(BillingProfile $billingProfile, Order $order): void
    {
        // Ensure that $0 invoices are not created
        $total = (float) $order->getSowAmount();
        if ($total < 0.01) {
            return;
        }

        $this->invoiced->Invoice->create([
            'customer' => $billingProfile->getInvoicedCustomer(),
            'name' => 'Statement of Work',
            'payment_terms' => 'NET 30',
            'items' => [
                [
                    'name' => 'Statement of Work',
                    'catalog_item' => self::SOW_ITEM,
                    'quantity' => 1,
                    'unit_cost' => $total,
                ],
            ],
            'send' => true,
        ]);
    }

    private function provisionNewAccount(NewAccount $newAccount, Order $order): void
    {
        // Do not provision immediately if start date is in future
        if ((int) $order->getStartDate()->format('Ymd') > (int) date('Ymd')) {
            // Move the order to open status now that the account information is added.
            $order->setStatus(OrderStatus::Open->value);
            /** @var ObjectManager $em */
            $em = $this->doctrine->getManagerForClass(get_class($order));
            $em->persist($order);
            $em->flush();

            return;
        }

        // Create the new account and update the order
        $result = $this->creator->create($newAccount);
        $order->setCreatedTenant($result->id);
        $this->markFulfilled($order);
    }

    /**
     * Sends an email confirmation to the creator of the order.
     */
    private function sendEmailNotification(Order $order): void
    {
        $user = $this->security->getUser();
        if (!($user instanceof User)) {
            throw new RuntimeException('Missing current user');
        }

        // Ensure the order has a valid billing profile
        $billingProfile = $this->getBillingProfile($order);

        $email = (new TemplatedEmail())
            ->to(new Address($user->getEmail(), (string) $user), new Address('chris@invoiced.com', 'Chris Couch'))
            ->subject('Sales Order: '.$order->getFormattedId())
            ->htmlTemplate('emails/order_confirmation.html.twig')
            ->context([
                'orderId' => $order->getId(),
                'orderNumber' => $order->getFormattedId(),
                'orderType' => $order->getFormattedType(),
                'customer' => $order->getCustomer(),
                'changeOrderType' => $order->getFormattedChangeOrderType(),
                'invoicedCustomerId' => $billingProfile->getInvoicedCustomer(),
                'startDate' => $order->getStartDate()->format('n/j/Y'),
                'salesRep' => $order->getSalesRep(),
            ])
            // TODO: need to figure out how to read MIME type when reading from a flysystem stream
            ->attach($this->readOrderFile($order), $order->getAttachmentName());
//            ->attach($this->readOrderFile($order), $order->getAttachmentName(), $attachment->getMimeType());

        if (in_array($order->getTypeEnum(), [OrderType::StatementOfWork, OrderType::NewAccountAndStatementOfWork])) {
            $email->addTo(new Address('support@invoiced.com', 'Invoiced Support'));
        }

        $this->mailer->send($email);
    }

    /**
     * Sends an email notification for new change orders.
     */
    private function sendChangeOrderEmailNotification(Order $order): void
    {
        $user = $this->security->getUser();
        if (!($user instanceof User)) {
            throw new RuntimeException('Missing current user');
        }

        // Ensure the order has a valid billing profile
        $billingProfile = $this->getBillingProfile($order);

        $email = (new TemplatedEmail())
            ->to(new Address($user->getEmail(), (string) $user), new Address('support@invoiced.com', 'Invoiced Support'))
            ->subject('Action Required - Sales Order: '.$order->getFormattedId())
            ->priority(Email::PRIORITY_HIGH)
            ->htmlTemplate('emails/order_billing_notification.html.twig')
            ->context([
                'orderId' => $order->getId(),
                'orderNumber' => $order->getFormattedId(),
                'orderType' => $order->getFormattedType(),
                'customer' => $order->getCustomer(),
                'changeOrderType' => $order->getFormattedChangeOrderType(),
                'invoicedCustomerId' => $billingProfile->getInvoicedCustomer(),
                'startDate' => $order->getStartDate()->format('n/j/Y'),
                'salesRep' => $order->getSalesRep(),
            ])
            // TODO: need to figure out how to read MIME type when reading from a flysystem stream
            ->attach($this->readOrderFile($order), $order->getAttachmentName());
//            ->attach($this->readOrderFile($order), $order->getAttachmentName(), $attachment->getMimeType());

        $this->mailer->send($email);
    }

    /**
     * Sends an email notification to Zapier that we have a new order.
     */
    private function sendToZapier(Order $order): void
    {
        $orderUrl = $this->urlGenerator->generate('shortcut_order', ['id' => $order->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $body = "Order ID: {$order->getFormattedId()}\n";
        $body .= "Type: {$order->getFormattedType()}\n";
        $body .= "Customer: {$order->getCustomer()}\n";
        $body .= "Should Bill: 0\n";
        $body .= "Create Service Order: 0\n";
        $body .= 'Order URL: '.$orderUrl."\n";
        $body .= 'Sales Rep: '.$order->getSalesRep()."\n";

        $email = (new Email())
            ->to($this->newOrderEmail)
            ->subject('New Sales Order')
            ->priority(Email::PRIORITY_HIGH)
            ->text($body)
            // TODO: need to figure out how to read MIME type when reading from a flysystem stream
            ->attach($this->readOrderFile($order), $order->getAttachmentName());
//            ->attach($this->readOrderFile($order), $order->getAttachmentName(), $attachment->getMimeType());
        $this->mailer->send($email);
    }

    /**
     * Marks an order as fulfilled.
     */
    private function markFulfilled(Order $order): void
    {
        $order->markFulfilledBySystem();
        /** @var ObjectManager $em */
        $em = $this->doctrine->getManagerForClass(get_class($order));
        $em->persist($order);
        $em->flush();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreOrderEvent::class => 'onPreOrderEvent',
            PostOrderEvent::class => 'onPostOrderEvent',
        ];
    }

    private function readOrderFile(Order $order): string
    {
        $stream = $this->fileStorage->resolveStream($order, 'attachment_file');
        if (null === $stream) {
            throw new NoFileFoundException(\sprintf('No file found in field "%s".', 'attachment_file'));
        }

        return (string) stream_get_contents($stream);
    }
}
