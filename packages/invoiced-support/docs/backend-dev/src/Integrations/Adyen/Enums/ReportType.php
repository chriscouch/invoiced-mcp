<?php

namespace App\Integrations\Adyen\Enums;

enum ReportType: string
{
    case BalancePlatformAccountingInteractive = 'balanceplatform_accounting_interactive_report';
    case BalancePlatformAccounting = 'balanceplatform_accounting_report';
    case BalancePlatformBalance = 'balanceplatform_balance_report';
    case BalancePlatformFee = 'balanceplatform_fee_report';
    case BalancePlatformPaymentInstrument = 'balanceplatform_payment_instrument_report';
    case BalancePlatformPayout = 'balanceplatform_payout_report';
    case BalancePlatformStatement = 'balanceplatform_statement_report';
}
