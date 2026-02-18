<?php

namespace App\Core\I18n;

use InvalidArgumentException;
use App\Core\Orm\Model;

/**
 * Manages our supported currencies using ISO 4217-3 currency codes.
 */
class Currencies
{
    /**
     * ISO-4217 numeric currency code.
     *
     * @var string[]
     */
    const NUMERIC_CODES = [
        'aed' => '784',
        'afa' => '971',
        'amd' => '051',
        'amk' => '894',
        'ang' => '532',
        'ars' => '032',
        'aud' => '036',
        'awg' => '533',
        'azn' => '944',
        'bam' => '977',
        'bbd' => '052',
        'bdt' => '050',
        'bgn' => '975',
        'bif' => '108',
        'bmd' => '060',
        'bnd' => '096',
        'bob' => '068',
        'brl' => '986',
        'bsd' => '044',
        'bwp' => '072',
        'byn' => '974',
        'byr' => '974',
        'bzd' => '084',
        'cad' => '124',
        'chf' => '756',
        'clp' => '152',
        'cny' => '156',
        'cop' => '170',
        'cpy' => '196',
        'crc' => '188',
        'csd' => '891',
        'cve' => '132',
        'czk' => '203',
        'djf' => '262',
        'dkk' => '208',
        'dop' => '214',
        'dzd' => '012',
        'eek' => '233',
        'egp' => '818',
        'ern' => '232',
        'etb' => '230',
        'eur' => '978',
        'fjd' => '242',
        'fkp' => '238',
        'gbp' => '826',
        'gel' => '981',
        'ghc' => '288',
        'ghs' => '288',
        'gip' => '292',
        'gmd' => '270',
        'gnf' => '324',
        'gtq' => '320',
        'gwp' => '624',
        'gyd' => '328',
        'hkd' => '344',
        'hnl' => '340',
        'hrk' => '191',
        'htg' => '332',
        'huf' => '348',
        'idr' => '360',
        'ils' => '376',
        'inr' => '356',
        'isk' => '352',
        'jmd' => '388',
        'jpy' => '392',
        'kes' => '404',
        'kgs' => '417',
        'khr' => '116',
        'kmf' => '174',
        'kpw' => '408',
        'krw' => '410',
        'kwd' => '414',
        'kyd' => '136',
        'kzt' => '398',
        'lak' => '418',
        'lbp' => '422',
        'lkr' => '144',
        'ltl' => '440',
        'lvl' => '428',
        'mad' => '504',
        'mdl' => '498',
        'mga' => '969',
        'mgf' => '450',
        'mkd' => '807',
        'mnt' => '496',
        'mop' => '446',
        'mro' => '478',
        'mtl' => '470',
        'mur' => '480',
        'mvr' => '462',
        'mwk' => '454',
        'mxn' => '484',
        'myr' => '458',
        'mzm' => '508',
        'mzn' => '508',
        'nad' => '516',
        'ngn' => '566',
        'nio' => '558',
        'nok' => '578',
        'npr' => '524',
        'nzd' => '554',
        'omr' => '512',
        'pab' => '590',
        'pen' => '604',
        'php' => '608',
        'pkr' => '586',
        'pln' => '985',
        'pyg' => '600',
        'qar' => '634',
        'ron' => '946',
        'rub' => '643',
        'rwf' => '646',
        'sar' => '682',
        'sbd' => '090',
        'scr' => '690',
        'sek' => '752',
        'sgd' => '702',
        'shp' => '654',
        'sit' => '705',
        'skk' => '703',
        'sll' => '694',
        'sos' => '706',
        'srd' => '968',
        'std' => '678',
        'svc' => '222',
        'szl' => '748',
        'thb' => '764',
        'top' => '776',
        'try' => '949',
        'ttd' => '780',
        'twd' => '901',
        'tzs' => '834',
        'uah' => '980',
        'ugx' => '800',
        'usd' => '840',
        'uyu' => '858',
        'uzs' => '860',
        'veb' => '862',
        'vef' => '862',
        'vnd' => '704',
        'vuv' => '548',
        'wst' => '882',
        'xaf' => '950',
        'xcd' => '951',
        'xcg' => '532',
        'xof' => '952',
        'xpf' => '953',
        'yer' => '886',
        'zar' => '710',
        'zmw' => '894',
        'zwd' => '716',
        'zwl' => '716',
    ];

    /**
     * This is based off of the Orbital Gateway XML Specification
     * Currency Codes and Exponents table.
     */
    const EXPONENTS = [
        'dzd' => '2',
        'ars' => '2',
        'amd' => '2',
        'awg' => '2',
        'aud' => '2',
        'azn' => '2',
        'bsd' => '2',
        'bdt' => '2',
        'bbd' => '2',
        'byn' => '0',
        'bzd' => '2',
        'bmd' => '2',
        'bob' => '2',
        'bwp' => '2',
        'brl' => '2',
        'gbp' => '2',
        'bnd' => '2',
        'bgn' => '2',
        'bif' => '0',
        'xof' => '0',
        'xaf' => '0',
        'xpf' => '0',
        'cad' => '2',
        'khr' => '2',
        'cve' => '2',
        'kyd' => '2',
        'clp' => '2',
        'cny' => '2',
        'cop' => '2',
        'kmf' => '0',
        'crc' => '2',
        'czk' => '2',
        'dkk' => '2',
        'djf' => '0',
        'dop' => '2',
        'xcd' => '2',
        'egp' => '2',
        'svc' => '2',
        'eek' => '2',
        'etb' => '2',
        'eur' => '2',
        'fkp' => '2',
        'fjd' => '2',
        'gmd' => '2',
        'gel' => '2',
        'ghs' => '2',
        'gip' => '2',
        'gtq' => '2',
        'gnf' => '0',
        'gwp' => '2',
        'gyd' => '2',
        'htg' => '2',
        'hnl' => '2',
        'hkd' => '2',
        'huf' => '2',
        'isk' => '2',
        'inr' => '2',
        'idr' => '2',
        'ils' => '2',
        'jmd' => '2',
        'jpy' => '0',
        'kzt' => '2',
        'kes' => '2',
        'kgs' => '2',
        'lak' => '2',
        'lvl' => '2',
        'lbp' => '2',
        'ltl' => '2',
        'mop' => '2',
        'mgf' => '1',
        'mwk' => '2',
        'myr' => '2',
        'mvr' => '2',
        'mro' => '1',
        'mur' => '2',
        'mxn' => '2',
        'mdl' => '2',
        'mnt' => '2',
        'mad' => '2',
        'mzn' => '2',
        'nad' => '2',
        'npr' => '2',
        'ang' => '2',
        'nzd' => '2',
        'nio' => '2',
        'ngn' => '2',
        'nok' => '2',
        'pkr' => '2',
        'pab' => '2',
        'pyg' => '0',
        'pen' => '2',
        'php' => '2',
        'pln' => '2',
        'qar' => '2',
        'ron' => '2',
        'rub' => '2',
        'rwf' => '0',
        'shp' => '2',
        'wst' => '2',
        'std' => '2',
        'sar' => '2',
        'scr' => '2',
        'sll' => '2',
        'sgd' => '2',
        'sbd' => '2',
        'sos' => '2',
        'zar' => '2',
        'krw' => '0',
        'lkr' => '2',
        'szl' => '2',
        'sek' => '2',
        'chf' => '2',
        'twd' => '2',
        'tzs' => '2',
        'thb' => '2',
        'top' => '2',
        'ttd' => '2',
        'try' => '2',
        'ugx' => '0',
        'uah' => '2',
        'aed' => '2',
        'uyu' => '2',
        'usd' => '2',
        'uzs' => '2',
        'vuv' => '0',
        'vef' => '2',
        'vnd' => '2',
        'xcg' => '2',
        'yer' => '2',
        'zmw' => '2',
        'zwl' => '2',
    ];

    private const CURRENCIES = [
        'AED' => [
            'name' => 'United Arab Emirates Dirham',
            'symbol' => 'د.إ',
        ],
        'AFN' => [
            'name' => 'Afghanistan Afghani',
            'symbol' => 'AFN',
        ],
        'ALL' => [
            'name' => 'Albania Lek',
            'symbol' => 'Lek',
        ],
        'AMD' => [
            'name' => 'Armenia Dram',
            'symbol' => 'AMD',
        ],
        'ANG' => [
            'name' => 'Netherlands Antilles Guilder',
            'symbol' => 'ƒ',
        ],
        'AOA' => [
            'name' => 'Angola Kwanza',
            'symbol' => 'Kz',
        ],
        'ARS' => [
            'name' => 'Argentina Peso',
            'symbol' => '$',
        ],
        'AUD' => [
            'name' => 'Australia Dollar',
            'symbol' => '$',
        ],
        'AWG' => [
            'name' => 'Aruba Guilder',
            'symbol' => 'ƒ',
        ],
        'AZN' => [
            'name' => 'Azerbaijan New Manat',
            'symbol' => 'ман',
        ],
        'BAM' => [
            'name' => 'Bosnia and Herzegovina Convertible Marka',
            'symbol' => 'KM',
        ],
        'BBD' => [
            'name' => 'Barbados Dollar',
            'symbol' => '$',
        ],
        'BDT' => [
            'name' => 'Bangladesh Taka',
            'symbol' => 'Tk',
        ],
        'BGN' => [
            'name' => 'Bulgaria Lev',
            'symbol' => 'лв',
        ],
        'BHD' => [
            'name' => 'Bahrain Dinar',
            'symbol' => 'BHD',
        ],
        'BIF' => [
            'name' => 'Burundi Franc',
            'symbol' => 'BIF',
        ],
        'BMD' => [
            'name' => 'Bermuda Dollar',
            'symbol' => '$',
        ],
        'BND' => [
            'name' => 'Brunei Darussalam Dollar',
            'symbol' => '$',
        ],
        'BOB' => [
            'name' => 'Bolivia Boliviano',
            'symbol' => '$b',
        ],
        'BRL' => [
            'name' => 'Brazil Real',
            'symbol' => 'R$',
        ],
        'BSD' => [
            'name' => 'Bahamas Dollar',
            'symbol' => '$',
        ],
        'BTC' => [
            'name' => 'Bitcoin',
            'symbol' => 'BTC',
        ],
        'BTN' => [
            'name' => 'Bhutan Ngultrum',
            'symbol' => 'BTN',
        ],
        'BWP' => [
            'name' => 'Botswana Pula',
            'symbol' => 'P',
        ],
        'BYN' => [
            'name' => 'Belarusian Ruble',
            'symbol' => 'Br',
        ],
        'BYR' => [
            'name' => 'Belarus Ruble',
            'symbol' => 'p.',
        ],
        'BZD' => [
            'name' => 'Belize Dollar',
            'symbol' => 'BZ$',
        ],
        'CAD' => [
            'name' => 'Canada Dollar',
            'symbol' => '$',
        ],
        'CDF' => [
            'name' => 'Congo/Kinshasa Franc',
            'symbol' => 'CDF',
        ],
        'CHF' => [
            'name' => 'Switzerland Franc',
            'symbol' => 'CHF',
        ],
        'CLP' => [
            'name' => 'Chile Peso',
            'symbol' => '$',
        ],
        'CNY' => [
            'name' => 'China Yuan Renminbi',
            'symbol' => '¥',
        ],
        'COP' => [
            'name' => 'Colombia Peso',
            'symbol' => 'p.',
        ],
        'CRC' => [
            'name' => 'Costa Rica Colon',
            'symbol' => '₡',
        ],
        'CUC' => [
            'name' => 'Cuba Convertible Peso',
            'symbol' => 'CUC',
        ],
        'CUP' => [
            'name' => 'Cuba Peso',
            'symbol' => '₱',
        ],
        'CVE' => [
            'name' => 'Cape Verde Escudo',
            'symbol' => 'CVE',
        ],
        'CZK' => [
            'name' => 'Czech ReKoruna',
            'symbol' => 'Kč',
        ],
        'DJF' => [
            'name' => 'Djibouti Franc',
            'symbol' => 'CHF',
        ],
        'DKK' => [
            'name' => 'Denmark Krone',
            'symbol' => 'kr',
        ],
        'DOP' => [
            'name' => 'Dominican RePeso',
            'symbol' => 'RD$',
        ],
        'DZD' => [
            'name' => 'Algeria Dinar',
            'symbol' => 'DZD',
        ],
        'EGP' => [
            'name' => 'Egypt Pound',
            'symbol' => 'E£',
        ],
        'ERN' => [
            'name' => 'Eritrea Nakfa',
            'symbol' => 'ERN',
        ],
        'ETB' => [
            'name' => 'Ethiopia Birr',
            'symbol' => 'ETB',
        ],
        'EUR' => [
            'name' => 'Euro Member Countries',
            'symbol' => '€',
        ],
        'FJD' => [
            'name' => 'Fiji Dollar',
            'symbol' => '$',
        ],
        'FKP' => [
            'name' => 'Falkland Islands (Malvinas) Pound',
            'symbol' => '£',
        ],
        'GBP' => [
            'name' => 'United Kingdom Pound',
            'symbol' => '£',
        ],
        'GEL' => [
            'name' => 'Georgia Lari',
            'symbol' => 'GEL',
        ],
        'GGP' => [
            'name' => 'Guernsey Pound',
            'symbol' => '£',
        ],
        'GHS' => [
            'name' => 'Ghana Cedi',
            'symbol' => 'GH¢',
        ],
        'GIP' => [
            'name' => 'Gibraltar Pound',
            'symbol' => '£',
        ],
        'GMD' => [
            'name' => 'Gambia Dalasi',
            'symbol' => 'GMD',
        ],
        'GNF' => [
            'name' => 'Guinea Franc',
            'symbol' => 'GNF',
        ],
        'GTQ' => [
            'name' => 'Guatemala Quetzal',
            'symbol' => 'Q',
        ],
        'GYD' => [
            'name' => 'Guyana Dollar',
            'symbol' => '$',
        ],
        'HKD' => [
            'name' => 'Hong Kong Dollar',
            'symbol' => 'HK$',
        ],
        'HNL' => [
            'name' => 'Honduras Lempira',
            'symbol' => 'L',
        ],
        'HRK' => [
            'name' => 'Croatia Kuna',
            'symbol' => 'kn',
        ],
        'HTG' => [
            'name' => 'Haiti Gourde',
            'symbol' => 'HTG',
        ],
        'HUF' => [
            'name' => 'Hungary Forint',
            'symbol' => 'Ft',
        ],
        'IDR' => [
            'name' => 'Indonesia Rupiah',
            'symbol' => 'Rp',
        ],
        'ILS' => [
            'name' => 'Israel Shekel',
            'symbol' => '₪',
        ],
        'IMP' => [
            'name' => 'Isle of Man Pound',
            'symbol' => '£',
        ],
        'INR' => [
            'name' => 'India Rupee',
            'symbol' => 'Rs',
        ],
        'IQD' => [
            'name' => 'Iraq Dinar',
            'symbol' => 'IQD',
        ],
        'IRR' => [
            'name' => 'Iran Rial',
            'symbol' => 'IRR',
        ],
        'ISK' => [
            'name' => 'Iceland Krona',
            'symbol' => 'kr',
        ],
        'JEP' => [
            'name' => 'Jersey Pound',
            'symbol' => '£',
        ],
        'JMD' => [
            'name' => 'Jamaica Dollar',
            'symbol' => 'J$',
        ],
        'JOD' => [
            'name' => 'Jordan Dinar',
            'symbol' => 'JOD',
        ],
        'JPY' => [
            'name' => 'Japan Yen',
            'symbol' => '¥',
        ],
        'KES' => [
            'name' => 'Kenya Shilling',
            'symbol' => 'KSh',
        ],
        'KGS' => [
            'name' => 'Kyrgyzstan Som',
            'symbol' => 'лв',
        ],
        'KHR' => [
            'name' => 'Cambodia Riel',
            'symbol' => '៛',
        ],
        'KMF' => [
            'name' => 'Comoros Franc',
            'symbol' => 'KMF',
        ],
        'KPW' => [
            'name' => 'Korea (North) Won',
            'symbol' => '₩',
        ],
        'KRW' => [
            'name' => 'Korea (South) Won',
            'symbol' => '₩',
        ],
        'KWD' => [
            'name' => 'Kuwait Dinar',
            'symbol' => 'ك',
        ],
        'KYD' => [
            'name' => 'Cayman Islands Dollar',
            'symbol' => '$',
        ],
        'KZT' => [
            'name' => 'Kazakhstan Tenge',
            'symbol' => 'лв',
        ],
        'LAK' => [
            'name' => 'Laos Kip',
            'symbol' => '₭',
        ],
        'LBP' => [
            'name' => 'Lebanon Pound',
            'symbol' => '£',
        ],
        'LKR' => [
            'name' => 'Sri Lanka Rupee',
            'symbol' => 'Rs',
        ],
        'LRD' => [
            'name' => 'Liberia Dollar',
            'symbol' => '$',
        ],
        'LSL' => [
            'name' => 'Lesotho Loti',
            'symbol' => 'LSL',
        ],
        'LTL' => [
            'name' => 'Lithuania Litas',
            'symbol' => 'Lt',
        ],
        'LVL' => [
            'name' => 'Latvia Lat',
            'symbol' => 'Ls',
        ],
        'LYD' => [
            'name' => 'Libya Dinar',
            'symbol' => 'LD',
        ],
        'MAD' => [
            'name' => 'Morocco Dirham',
            'symbol' => 'MAD',
        ],
        'MDL' => [
            'name' => 'Moldova Leu',
            'symbol' => 'MDL',
        ],
        'MGA' => [
            'name' => 'Madagascar Ariary',
            'symbol' => 'MGA',
        ],
        'MKD' => [
            'name' => 'Macedonia Denar',
            'symbol' => 'ден',
        ],
        'MMK' => [
            'name' => 'Myanmar (Burma) Kyat',
            'symbol' => 'MMK',
        ],
        'MNT' => [
            'name' => 'Mongolia Tughrik',
            'symbol' => '₮',
        ],
        'MOP' => [
            'name' => 'Macau Pataca',
            'symbol' => 'MOP',
        ],
        'MRO' => [
            'name' => 'Mauritania Ouguiya',
            'symbol' => 'MRO',
        ],
        'MUR' => [
            'name' => 'Mauritius Rupee',
            'symbol' => 'Rs',
        ],
        'MVR' => [
            'name' => 'Maldives (Maldive Islands) Rufiyaa',
            'symbol' => 'MVR',
        ],
        'MWK' => [
            'name' => 'Malawi Kwacha',
            'symbol' => 'MWK',
        ],
        'MXN' => [
            'name' => 'Mexico Peso',
            'symbol' => '$',
        ],
        'MYR' => [
            'name' => 'Malaysia Ringgit',
            'symbol' => 'RM',
        ],
        'MZN' => [
            'name' => 'Mozambique Metical',
            'symbol' => 'MT',
        ],
        'NAD' => [
            'name' => 'Namibia Dollar',
            'symbol' => 'N$',
        ],
        'NGN' => [
            'name' => 'Nigeria Naira',
            'symbol' => '₦',
        ],
        'NIO' => [
            'name' => 'Nicaragua Cordoba',
            'symbol' => 'C$',
        ],
        'NOK' => [
            'name' => 'Norway Krone',
            'symbol' => 'kr',
        ],
        'NPR' => [
            'name' => 'Nepal Rupee',
            'symbol' => 'Rs',
        ],
        'NZD' => [
            'name' => 'New Zealand Dollar',
            'symbol' => '$',
        ],
        'OMR' => [
            'name' => 'Oman Rial',
            'symbol' => 'OMR',
        ],
        'PAB' => [
            'name' => 'Panama Balboa',
            'symbol' => 'B/.',
        ],
        'PEN' => [
            'name' => 'Peru Nuevo Sol',
            'symbol' => 'S/.',
        ],
        'PGK' => [
            'name' => 'Papua New Guinea Kina',
            'symbol' => 'PGK',
        ],
        'PHP' => [
            'name' => 'Philippines Peso',
            'symbol' => '₱',
        ],
        'PKR' => [
            'name' => 'Pakistan Rupee',
            'symbol' => 'Rs',
        ],
        'PLN' => [
            'name' => 'Poland Zloty',
            'symbol' => 'zł',
        ],
        'PYG' => [
            'name' => 'Paraguay Guarani',
            'symbol' => 'Gs',
        ],
        'QAR' => [
            'name' => 'Qatar Riyal',
            'symbol' => 'QAR',
        ],
        'RON' => [
            'name' => 'Romania New Leu',
            'symbol' => 'lei',
        ],
        'RSD' => [
            'name' => 'Serbia Dinar',
            'symbol' => 'Дин.',
        ],
        'RUB' => [
            'name' => 'Russia Ruble',
            'symbol' => 'руб',
        ],
        'RWF' => [
            'name' => 'Rwanda Franc',
            'symbol' => 'RWF',
        ],
        'SAR' => [
            'name' => 'Saudi Arabia Riyal',
            'symbol' => 'SAR',
        ],
        'SBD' => [
            'name' => 'Solomon Islands Dollar',
            'symbol' => '$',
        ],
        'SCR' => [
            'name' => 'Seychelles Rupee',
            'symbol' => 'Rs',
        ],
        'SDG' => [
            'name' => 'Sudan Pound',
            'symbol' => 'SDG',
        ],
        'SEK' => [
            'name' => 'Sweden Krona',
            'symbol' => 'kr',
        ],
        'SGD' => [
            'name' => 'Singapore Dollar',
            'symbol' => '$',
        ],
        'SHP' => [
            'name' => 'Saint Helena Pound',
            'symbol' => '£',
        ],
        'SLL' => [
            'name' => 'Sierra Leone Leone',
            'symbol' => 'SLL',
        ],
        'SOS' => [
            'name' => 'Somalia Shilling',
            'symbol' => 'S',
        ],
        'SPL' => [
            'name' => 'Seborga Luigino',
            'symbol' => 'SPL',
        ],
        'SRD' => [
            'name' => 'Suriname Dollar',
            'symbol' => '$',
        ],
        'STD' => [
            'name' => 'São Tomé and Príncipe Dobra',
            'symbol' => 'STD',
        ],
        'SVC' => [
            'name' => 'El Salvador Colon',
            'symbol' => '$',
        ],
        'SYP' => [
            'name' => 'Syria Pound',
            'symbol' => '£',
        ],
        'SZL' => [
            'name' => 'Swaziland Lilangeni',
            'symbol' => 'SZL',
        ],
        'THB' => [
            'name' => 'Thailand Baht',
            'symbol' => '฿',
        ],
        'TJS' => [
            'name' => 'Tajikistan Somoni',
            'symbol' => 'TJS',
        ],
        'TMT' => [
            'name' => 'Turkmenistan Manat',
            'symbol' => 'TMT',
        ],
        'TND' => [
            'name' => 'Tunisia Dinar',
            'symbol' => 'DT',
        ],
        'TOP' => [
            'name' => 'Tonga Paanga',
            'symbol' => 'TOP',
        ],
        'TRY' => [
            'name' => 'Turkey Lira',
            'symbol' => 'TRY',
        ],
        'TTD' => [
            'name' => 'Trinidad and Tobago Dollar',
            'symbol' => 'TT$',
        ],
        'TVD' => [
            'name' => 'Tuvalu Dollar',
            'symbol' => '$',
        ],
        'TWD' => [
            'name' => 'Taiwan New Dollar',
            'symbol' => 'NT$',
        ],
        'TZS' => [
            'name' => 'Tanzania Shilling',
            'symbol' => 'TSh',
        ],
        'UAH' => [
            'name' => 'Ukraine Hryvna',
            'symbol' => '₴',
        ],
        'UGX' => [
            'name' => 'Uganda Shilling',
            'symbol' => 'USh',
        ],
        'USD' => [
            'name' => 'United States Dollar',
            'symbol' => '$',
        ],
        'UYU' => [
            'name' => 'Uruguay Peso',
            'symbol' => '$U',
        ],
        'UZS' => [
            'name' => 'Uzbekistan Som',
            'symbol' => 'лв',
        ],
        'VEF' => [
            'name' => 'Venezuela Bolivar',
            'symbol' => 'Bs',
        ],
        'VND' => [
            'name' => 'Viet Nam Dong',
            'symbol' => '₫',
        ],
        'VUV' => [
            'name' => 'Vanuatu Vatu',
            'symbol' => 'VUV',
        ],
        'WST' => [
            'name' => 'Samoa Tala',
            'symbol' => 'WST',
        ],
        'XAF' => [
            'name' => 'Central African CFA Franc BEAC',
            'symbol' => 'XAF',
        ],
        'XCD' => [
            'name' => 'East Caribbean Dollar',
            'symbol' => '$',
        ],
        'XCG' => [
            'name' => 'Caribbean Guilder',
            'symbol' => 'Cg',
        ],
        'XDR' => [
            'name' => 'International Monetary Fund (IMF) Special Drawing Rights',
            'symbol' => 'XDR',
        ],
        'XOF' => [
            'name' => 'Communauté Financière Africaine (BCEAO) Franc',
            'symbol' => 'XOF',
        ],
        'XPF' => [
            'name' => 'Comptoirs Français du Pacifique (CFP) Franc',
            'symbol' => 'XPF',
        ],
        'YER' => [
            'name' => 'Yemen Rial',
            'symbol' => 'YER',
        ],
        'ZAR' => [
            'name' => 'South Africa Rand',
            'symbol' => 'R',
        ],
        'ZMW' => [
            'name' => 'Zambia Kwacha',
            'symbol' => 'ZK',
        ],
        'ZWD' => [
            'name' => 'Zimbabwe Dollar',
            'symbol' => 'Z$',
        ],
    ];

    /**
     * Gets all supported currencies.
     */
    public static function all(): array
    {
        return self::CURRENCIES;
    }

    /**
     * Gets the configuration for a currency code.
     *
     * @throws InvalidArgumentException if the currency does not exist
     */
    public static function get(string $currencyCode): array
    {
        $currencyCodeUpper = strtoupper($currencyCode);
        if (!isset(self::CURRENCIES[$currencyCodeUpper])) {
            throw new InvalidArgumentException("Invalid currency code: $currencyCode");
        }

        return self::CURRENCIES[$currencyCodeUpper];
    }

    /**
     * Checks if a currency code is valid.
     */
    public static function exists(string $currencyCode): bool
    {
        $currencyCodeUpper = strtoupper($currencyCode);

        return isset(self::CURRENCIES[$currencyCodeUpper]);
    }

    /**
     * Currency validator for use in models. Requires a valid
     * 3-digit currency code.
     */
    public static function validateCurrency(?string &$currencyCode, array $options, Model $model): bool
    {
        $nullable = $options['nullable'] ?? false;
        if ($nullable && null === $currencyCode) {
            return true;
        }

        if (!self::exists((string) $currencyCode)) {
            $model->getErrors()->add('Invalid currency code: '.$currencyCode);

            return false;
        }

        // internally the currency code is stored as lowercase
        $currencyCode = strtolower((string) $currencyCode);

        return true;
    }
}
