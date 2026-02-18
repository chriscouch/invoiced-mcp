<?php

namespace App\Twig;

use App\Service\IpInfoLookup;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    public function __construct(private IpInfoLookup $ipInfoLookup)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('ipInfoLink', [$this, 'ipInfoLink'], [
                'is_safe' => ['html'],
            ]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_numeric', [$this, 'isNumeric'], []),
        ];
    }

    public function ipInfoLink(string $value): string
    {
        return $this->ipInfoLookup->makeIpInfoLink($value) ?: '';
    }

    public function isNumeric(mixed $value): bool
    {
        return is_numeric($value);
    }
}
