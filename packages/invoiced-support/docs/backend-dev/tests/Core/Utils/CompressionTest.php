<?php

namespace App\Tests\Core\Utils;

use App\Core\Utils\Compression;
use App\Tests\AppTestCase;

class CompressionTest extends AppTestCase
{
    public function testCompression(): void
    {
        $original = '{"data":{"object":{"attempt_count":0,"autopay":false,"balance":58,"chase":false,"closed":false,"created_at":1644185404,"currency":"usd","customer":{"ach_gateway_id":null,"active":true,"address1":null,"address2":null,"attention_to":null,"autopay":false,"autopay_delay_days":-1,"avalara_entity_use_code":null,"avalara_exemption_number":null,"bill_to_parent":false,"cc_gateway_id":null,"chase":true,"chasing_cadence":82,"city":null,"consolidated":false,"country":"US","created_at":1565119007,"credit_hold":false,"credit_limit":null,"currency":"usd","email":"test@example.com","id":464576,"language":null,"late_fee_schedule_id":null,"name":"test","next_chase_step":null,"notes":"InvoiceINV-0275paidinfull","number":"CUST-0046","owner":null,"parent_customer":null,"payment_terms":null,"phone":null,"postal_code":null,"state":null,"tax_id":null,"taxable":true,"taxes":[],"type":"company","updated_at":1613221215,"object":"customer","statement_pdf_url":"https://acme.sandbox.invoiced.com/statements/FSt1PCWss50IPCU50N3yHcRu/pdf","sign_up_url":"https://acme.sandbox.invoiced.com/sign_up/FSt1PCWss50IPCU50N3yHcRu","payment_source":{"brand":"JCB","chargeable":true,"created_at":1565119009,"exp_month":2,"exp_year":2020,"failure_reason":null,"funding":"credit","gateway":"braintree","gateway_customer":null,"gateway_id":"kq9tz9","id":6280,"last4":"0505","merchant_account":264,"receipt_email":"test@example.com","updated_at":1608163408,"object":"card"},"sign_up_page":2240,"metadata":{}},"date":1644182208,"draft":false,"due_date":1646774208,"id":7364665,"late_fees":true,"name":"Basic","needs_attention":false,"next_chase_on":null,"next_payment_attempt":null,"notes":null,"number":"INV-0595","paid":false,"payment_plan":null,"payment_terms":"3%15NET30","purchase_order":null,"status":"sent","subscription":10179,"subtotal":58,"total":58,"updated_at":1644188404,"items":[{"amount":39,"catalog_item":null,"created_at":1644185404,"description":"","discountable":true,"id":49046745,"name":"Basic","plan":"basic","quantity":1,"taxable":true,"type":"plan","unit_cost":39,"updated_at":1644185404,"object":"line_item","metadata":{},"discounts":[],"taxes":[],"subscription":10179,"period_start":1644182208,"period_end":1646601407,"prorated":false},{"amount":19,"catalog_item":"ipad-license","created_at":1644185404,"description":"","discountable":true,"id":49046746,"name":"iPadLicense","quantity":1,"taxable":true,"type":null,"unit_cost":19,"updated_at":1644185404,"object":"line_item","metadata":{},"discounts":[],"taxes":[],"subscription":10179,"period_start":1644182208,"period_end":1646601407,"prorated":false}],"discounts":[],"taxes":[],"shipping":[],"object":"invoice","url":"https://acme.sandbox.invoiced.com/invoices/1IXZ4Bhvcd338Yv2uMf1Am07","pdf_url":"https://acme.sandbox.invoiced.com/invoices/1IXZ4Bhvcd338Yv2uMf1Am07/pdf","csv_url":"https://acme.sandbox.invoiced.com/invoices/1IXZ4Bhvcd338Yv2uMf1Am07/csv","payment_url":"https://acme.sandbox.invoiced.com/invoices/1IXZ4Bhvcd338Yv2uMf1Am07/payment","ship_to":null,"payment_source":null,"metadata":{}},"previous":{"status":"not_sent","updated_at":1644185404}},"id":20261238,"timestamp":1644188404,"type":"invoice.updated","user":{"created_at":null,"email":null,"first_name":null,"id":-2,"last_name":null,"updated_at":null,"two_factor_enabled":false,"registered":true}}';
        $this->assertFalse(Compression::isCompressed($original));

        $compressed = Compression::compress($original);
        $this->assertTrue(Compression::isCompressed($compressed));
        $this->assertNotEquals($compressed, $original);
        $this->assertLessThan(strlen($original), strlen($compressed), 'The compressed data should be smaller than the original data.');

        $decompressed = Compression::decompress($compressed);
        $this->assertEquals($original, $decompressed);
        $this->assertFalse(Compression::isCompressed($decompressed));

        $this->assertEquals($original, Compression::decompressIfNeeded($original));
        $this->assertEquals($original, Compression::decompressIfNeeded($compressed));
    }
}
