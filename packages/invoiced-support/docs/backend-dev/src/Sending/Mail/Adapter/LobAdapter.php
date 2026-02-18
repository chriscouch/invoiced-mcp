<?php

namespace App\Sending\Mail\Adapter;

use App\Integrations\Lob\LobAccount;
use App\Sending\Mail\Exceptions\SendLetterException;
use App\Sending\Mail\Interfaces\AdapterInterface;
use CommerceGuys\Addressing\Address;
use DateTime;
use App\Core\Utils\InfuseUtility as Utility;
use Lob\Lob;
use mikehaertl\tmp\File;

/**
 * Letter mailing service powered by Lob.com.
 */
class LobAdapter implements AdapterInterface
{
    public function send(Address $from, Address $to, File $pdf, string $description): array
    {
        $lobAccount = LobAccount::queryWithCurrentTenant()->oneOrNull();
        if (!$lobAccount) {
            throw new SendLetterException('You must first configure Lob in Settings > Integrations to send letters.');
        }

        $id = strtolower(Utility::guid(false));
        $client = $this->getClient($lobAccount);
        $params = [
            'description' => $description,
            'address_placement' => 'insert_blank_page',
            'to[name]' => substr($to->getGivenName(), 0, 40),
            'to[address_line1]' => $to->getAddressLine1(),
            'to[address_line2]' => $to->getAddressLine2(),
            'to[address_city]' => $to->getLocality(),
            'to[address_zip]' => $to->getPostalCode(),
            'to[address_state]' => $to->getAdministrativeArea(),
            'to[address_country]' => $to->getCountryCode(),
            'from[name]' => substr($from->getGivenName(), 0, 40),
            'from[address_line1]' => $from->getAddressLine1(),
            'from[address_line2]' => $from->getAddressLine2(),
            'from[address_city]' => $from->getLocality(),
            'from[address_zip]' => $from->getPostalCode(),
            'from[address_state]' => $from->getAdministrativeArea(),
            'from[address_country]' => $from->getCountryCode(),
            'file' => '@'.$pdf,
            'color' => $lobAccount->use_color,
            'return_envelope' => $lobAccount->return_envelopes,
            'custom_envelope' => $lobAccount->custom_envelope ?: null,
            'perforated_page' => $lobAccount->return_envelopes ? 1 : null,
            'use_type' => 'operational',
            'metadata' => [
                'invoiced_id' => $id,
            ],
        ];

        // send through lob
        try {
            $lobLetter = $client->letters()->create($params);
        } catch (\Exception $e) {
            throw new SendLetterException($e->getMessage(), $e->getCode(), $e);
        }

        // parse the response
        $deliveryDate = DateTime::createFromFormat('Y-m-d', $lobLetter['expected_delivery_date']);

        return [
            'id' => $id,
            'expected_delivery_date' => $deliveryDate ? $deliveryDate->getTimestamp() : null,
            'lob_id' => $lobLetter['id'],
        ];
    }

    private function getClient(LobAccount $lobAccount): Lob
    {
        return new Lob($lobAccount->key);
    }

    public function getDetail(string $id): array
    {
        $lobAccount = LobAccount::queryWithCurrentTenant()->oneOrNull();
        if (!$lobAccount) {
            return [];
        }

        $client = $this->getClient($lobAccount);

        try {
            return $client->letters()->get($id);
        } catch (\Exception) {
            return [];
        }
    }
}
