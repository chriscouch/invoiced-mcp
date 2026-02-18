<?php

namespace App\Core\Templating;

use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Sandbox\SecurityPolicy;

/**
 * Sandbox Twig to protect against malicious code
 * in user-supplied Twig templates.
 */
class TwigSandboxing
{
    private static array $allowedTags = [
        'if',
        'for',
        'set',
    ];

    private static array $allowedFilters = [
        'abs',
        'batch',
        'balance',
        'capitalize',
        'credit_balance',
        'date',
        'date_modify',
        'default',
        'escape',
        'first',
        'format',
        'join',
        'last',
        'length',
        'lower',
        'merge',
        'money',
        'money_unit_cost',
        'nl2br',
        'number_format',
        'number_format_no_round',
        'raw',
        'replace',
        'reverse',
        'round',
        'slice',
        'sort',
        'split',
        'title',
        'trim',
        'upper',
    ];

    private static array $allowedFunctions = [
        'cycle',
        'date',
        'dump_scope',
        'dump',
        'max',
        'min',
        'random',
        'range',
        'trans',
        'transchoice',
    ];

    public static function install(Environment $twig): void
    {
        $methods = [];
        $properties = [];
        $policy = new SecurityPolicy(self::$allowedTags, self::$allowedFilters, $methods, $properties, self::$allowedFunctions);
        $sandbox = new SandboxExtension($policy, true);
        $twig->addExtension($sandbox);
    }
}
