<?php

namespace App\Sending\Mail\Libs;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Member;
use App\Core\I18n\AddressFormatter;
use App\Core\Pdf\Exception\PdfException;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\ValueObjects\PendingEvent;
use App\Sending\Mail\Adapter\LobAdapter;
use App\Sending\Mail\Exceptions\SendLetterException;
use App\Sending\Mail\Models\Letter;
use App\Statements\Libs\AbstractStatement;
use CommerceGuys\Addressing\Address;
use mikehaertl\tmp\File;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use App\Core\Orm\ACLModelRequester;

/**
 * Sends letters to a recipient.
 */
class LetterSender implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private LobAdapter $lob, private EventSpool $eventSpool)
    {
    }

    /**
     * Sends a letter to a customer.
     *
     * @param Invoice|AbstractStatement $document
     *
     * @throws SendLetterException
     */
    public function send(Customer $customer, $document, Address $from, Address $to): Letter
    {
        $company = $customer->tenant();
        if (!$company->features->has('letters')) {
            throw new SendLetterException('This account does not support sending letters.');
        }

        // validate the addresses
        if (!$from->getAddressLine1()) {
            throw new SendLetterException('Your business mailing address has not been set up. You can set up your from address in Settings > Business Profile');
        }

        if (!$to->getAddressLine1()) {
            throw new SendLetterException('The customer does not have a mailing address. You must add a mailing address before sending a letter.');
        }

        $description = 'Letter';
        if ($document instanceof Invoice) {
            $description = 'Invoice # '.$document->number;
        } elseif ($document instanceof AbstractStatement) {
            $description = $customer->name.' Statement';
        }

        try {
            if ($pdfBuilder = $document->getPdfBuilder()) {
                $pdf = new File($pdfBuilder->build($customer->getLocale()), 'pdf');
            } else {
                throw new SendLetterException('This document does not have a PDF');
            }
        } catch (PdfException $e) {
            $this->logger->error('Unable to build letter PDF', ['exception' => $e]);

            throw new SendLetterException('Unable to build letter PDF.');
        }

        $params = $this->lob->send($from, $to, $pdf, $description);

        if ($document instanceof Invoice && !$document->closed) {
            $document->sent = true;
            $document->saveOrFail();
        }

        // save the letter
        return $this->recordLetter($params, $customer, $document, $to);
    }

    /**
     * @param Invoice|AbstractStatement $document
     */
    private function recordLetter(array $params, Customer $customer, $document, Address $to): Letter
    {
        $letter = new Letter();
        $formatter = new AddressFormatter();
        $formatOptions = ['showTaxId' => false, 'showExtra' => false];
        $letter->to = $formatter->formatAddress($to, $formatOptions);
        $letter->state = 'queued';
        $letter->num_pages = 1; // TODO

        $requester = ACLModelRequester::get();
        if ($requester instanceof Member) {
            $letter->sent_by = $requester->user();
        }

        foreach ($params as $k => $v) {
            $letter->$k = $v;
        }

        if ($document instanceof Invoice) {
            $letter->related_to_type = ObjectType::Invoice->value;
            $letter->related_to_id = (int) $document->id();
        }

        $letter->saveOrFail();

        // record the event
        $associations = [
            ['customer', $customer->id()],
        ];

        if ($document instanceof Invoice) {
            $associations[] = ['invoice', $document->id()];
        }

        $pendingEvent = new PendingEvent(
            object: $letter,
            type: EventType::LetterSent,
            associations: $associations
        );
        $this->eventSpool->enqueue($pendingEvent);

        return $letter;
    }
}
